<?php

class eZTagFeed
{
	var $siteIni, $feedIni;

	function __construct()
	{
		$this->siteIni = eZINI::instance( 'site.ini' );
		$this->feedIni = eZINI::instance( 'eztagfeed.ini' );
	}
	
	/* function findTags  find tags by ID or keyword
	 * @param $id , ID of tag or keyword
	 * @return list of eZTagObjects
	 */
	function findTags( $id )
	{
		if( is_numeric( $id )) {
			$tags = array( eZTagsObject::fetch( $id ));
		} else {
			$tags = eZTagsObject::fetchByKeyword($id);
		}
		return $tags;
	}
	
	/* 
	 * function objectsFromTags finds objects related to any of the tags in the list
	 * @param array $tagList  list of eZTagsObject
	 * @return array of eZContentObjects
	 */
	function objectsFromTags( $tagList )
	{
		$objects = array();
		foreach( $tagList as $tag ) {
			$tagObjects = $tag->getRelatedObjects();
			foreach( $tagObjects as $object ) {
				// add to array with objID as key to avoid duplicates
				$objects[$object->ID] = $object;
			}
		}
		return $objects;
	}
	
	/*
	 * function objects2nodes
	 * A list of objects is returned from ezTags->RelatedObjects
	 * This function processes the list by 
	 * - sorting it by published date
	 * - filter on class type
	 * - limit to number of items as in feedIni
	 * - convert to list of nodes
	 * @param array of objects $items
	 * #return array of nodes
	 */
	function objects2nodes( $objectList )
	{
		// Sort by published/desc
		usort( $objectList, array( 'eZTagFeed', 'sortitems' ));
		
		$nodeList = array();
		$showHiddenNodes = $this->siteIni->variable( 'SiteAccessSettings', 'ShowHiddenNodes' );
		$itemCount = 0;
		$maxItems = $this->feedIni->variable( 'FilterSettings', 'limit' );
		$classFilter = $this->feedIni->variable( 'FilterSettings', 'class' );
		foreach ( $objectList as $object ) {
			// Skip if classtypes listed in classfilter and current classtype is not in it
			if( count( $classFilter ) > 0  
					&& !in_array( $object->attribute( 'class_identifier'), $classFilter )) {
				continue;
			}
			$node = $object->attribute( 'main_node' );
			// Skip if hidden nodes should not be shown and this node is invisible
			if(( $showHiddenNodes == 'false'
					|| empty( $showHiddenNodes )) && $node->IsInvisible ) {
				continue;
			}
			$nodeList[] = $node;
			$itemCount++;
			if( $maxItems > 0
					&& $itemCount >= $maxItems ) {
				break;
			}
		}
		return $nodeList;
	}
	
	/* function getMeta - create metadata array for RRS feed 
	 * @param string $keyword
	 * @return array with meta
	 */
	function getMeta( $keyword )
	{
		$locale = eZLocale::instance();
		$siteURL = $this->siteIni->variable( 'SiteSettings', 'SiteURL' );
		$metaData = $this->siteIni->variable( 'SiteSettings', 'MetaDataArray' );
		return array(
				'title' => sprintf( "%s - %s", $keyword, $this->siteIni->variable( 'SiteSettings', 'SiteName' )),
				'description' => sprintf( 'Items on %s related to %s', $siteURL, $keyword ),
				'language' => $locale->httpLocaleCode(),
				'authorName' => $metaData['author'],
				'authorMail' => $this->siteIni->variable( 'MailSettings', 'AdminEmail' ),
				'siteURL' => $siteURL
				);
	}

	/*
	 * function node2rss - create rss feed from nodelist
	 * @param $nodeList - list of nodes
	 * @param $meta  - meta information for rss list: title, description
	 */
	function node2rss( $nodeList, $meta )
	{
		$feed = new ezcFeed();
		$feed->title = $meta['title'];
		$feed->description = $meta['description'];
		$feed->language = $meta['language'];

		$link = $feed->add( 'link' );
		$link->href = sprintf( 'http://%s/',  $meta['siteURL']);

		// to add the <atom:link> element needed for RSS2
		$feed->id = sprintf( 'http://%s%s/', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );

		// required for ATOM
		$feed->updated = time();
		$author = $feed->add( 'author' );
		$author->email = $meta['authorMail'];
		$author->name = $meta['authorName'];

		$descriptionFields = $this->feedIni->variable( 'FeedSettings', 'description' );
		$contentFields = $this->feedIni->variable( 'FeedSettings', 'content' );;
		$catFields = $this->feedIni->variable( 'FeedSettings', 'category' );

		// convert objects to nodes, filter class types, and limit number
		foreach( $nodeList as $node ) {
			$item = $feed->add( 'item' );
			$item->title = $node->attribute('name' );
			$item->published = date( 'r', $node->ContentObject->Published );
			$link = $item->add( 'link' );
			$link->href = sprintf( 'http://%s/%s',
					$meta['siteURL'], $node->attribute( 'url_alias' ));
			$item->id = sprintf( 'http://%s/content/view/full/%d',
					$meta['siteURL'], $node->attribute( 'node_id' ));
			
			$map = $node->attribute( 'data_map' );
			$description = $this->preferedField( $descriptionFields, $map );
			$item->description = $description;

			$content = $this->preferedField( $contentFields, $map );
			if( !empty( $content )) {
				$module = $item->addModule( 'Content' );
				$module->encoded = $content;
			}

			$tagString = $this->preferedField( $catFields, $map );
			$tags = explode( '|#', $tagString );
			foreach( $tags as $tag ) {
				$cat = $item->add( 'category' );
				$cat->term = $tag;
			}
		}
		$rss = $feed->generate( 'rss2' );
		// add host to local links
		$rss = preg_replace( '#(src|href)=([\'"])/#i',
				sprintf( "$1=$2http:/%s/", $meta['siteURL'] ), $rss );
		return $rss;
	}
	
	/*
	 * function preferedField
	 * return the content of the first matching field as a string
	 * @param array $fieldList  list of fields in order of preference
	 * @param array $dataMap  object attributes
	 * @return selected attributes as string  
	 */
	function preferedField( $fieldList, $dataMap )
	{
		foreach( $fieldList as $field ) {
			if( array_key_exists( $field, $dataMap )) {
				$attr = $dataMap[$field];
				switch( $attr->DataTypeString ) {
					case 'ezxmltext' :
						$content = $attr->attribute( 'content' );
						$handler = $content->attribute( 'output' );
						$text = $handler->outputText();
						break;

					case 'eztags' :
						$content = $attr->attribute( 'content' );
						$text = $content->keywordString();
						break;
					default :
						$text = $attr->toString();
						break;
				}
				return html_entity_decode( $text, ENT_COMPAT, 'UTF-8' ) ;
			}
		}
		return '';
	}
	
	/*
	 * function sortItems - callback function to sort list of objects by date published
	 * @params object $a first contentobject
	 * @params object $b second contentobject
	 * @return booean $b newer than $a 
	 */
	function sortitems( $a, $b )
	{
		return $b->Published - $a->Published;
	}

}