<?php
//header( 'Content-type: text/plain' );
header("Content-Type: application/xml; charset=utf-8");

ob_start();
$http = eZHTTPTool::instance();

$tagFeed = new eZTagFeed;
$tags = $tagFeed->findTags( $Params["Parameters"][0] );
$meta = $tagFeed->getMeta($tags[0]->Keyword);

$objects = $tagFeed->objectsFromTags($tags);
$nodes = $tagFeed->objects2nodes($objects);
$rss = $tagFeed->node2rss($nodes, $meta);

ob_end_clean();

echo $rss;

eZExecution::cleanup();
eZExecution::setCleanExit();
exit();
?>