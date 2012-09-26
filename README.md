eztagfeed
=========

Create RSS feeds in eZ Publish using tags

This extension uses eztags, http://projects.ez.no/eztags, install and enable this first

- http://www.example.com/tagfeed/tag/1  gives an RSS feed of the latest items tagged with tag 1
- http://www.example.com/tagfeed/tree/1  gives an RSS feed of the latest items tagged with tag 1 
or any of the tags that are below tag 1 in the tag hierarchy

Instead of the tag id, you can also use the keyword, see the examples below

## Installation

Copy the files in the extension directory and enable the extension.

## Configuration

In the standard configuration, the RSS feed contains max. 20 items, and only folders, articles, and links.
You can change this by appending or overriding the eztagfeed.ini file, section FilterSettings.

## Example

Suppose you have the following tag structure, with the ID in brackets.

----
* Europe (1)
  * Netherlands (2)
    * Amsterdam (3)
    * The Hague (4)
  * Croatia (5)
    * Bol (6)

----

/tagfeed/tag/2 or /tagfeed/tag/Netherlands will give an RSS feed of items tagged with Netherlands  
/tagfeed/tree/2 or /tagfeed/tree/Netherlands will give an RSS feed of items tagged with Netherlands, Amsterdam or The Hague.  
/tagfeed/tree/1 or /tagfeed/tree/Europe will also include the items tagged with Europe, Croatia or Bol  
