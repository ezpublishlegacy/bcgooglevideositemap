<?php
/**
 * File containing the bcgenerategooglevideositemapfromobjects.php cronjob part
 *
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2016 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.0.4
 * @package bcgooglevideositemap
 */

// Alert user
if( !$isQuiet )
{
    $cli->output( "Generating Video Sitemap ...\n" );
}

// Fetch ini settings
$ini = eZINI::instance( 'site.ini' );
$sitemapINI = eZINI::instance( 'bcgooglevideositemap.ini' );

// Test for and fetch ini settings
if( $sitemapINI->hasVariable( 'BCGoogleVideoSitemapSettings', 'SitemapRootNodeID' ) &&
    $sitemapINI->hasVariable( 'BCGoogleVideoSitemapSettings', 'Filename' ) &&
    $sitemapINI->hasVariable( 'BCGoogleVideoSitemapSettings', 'Path' ) &&
    $sitemapINI->hasVariable( 'Classes', 'ClassFilterType' ) &&
    $sitemapINI->hasVariable( 'Classes', 'ClassFilterArray' ) &&
    $sitemapINI->hasVariable( 'BCGoogleVideoSitemapSettings', 'SiteURLProtocol' ) &&
    $sitemapINI->hasVariable( 'BCGoogleVideoSitemapSettings', 'SiteSectionExcludeID' ) &&
    $sitemapINI->hasVariable( 'BCGoogleVideoSitemapSettings', 'SiteFetchDepth' ) &&
    $sitemapINI->hasVariable( 'BCGoogleVideoSitemapSettings', 'SiteFetchLimit' ) &&
    $ini->hasVariable( 'SiteSettings', 'SiteURL' ) )
{
    $sitemapRootNodeID = $sitemapINI->variable( 'BCGoogleVideoSitemapSettings', 'SitemapRootNodeID' );
    $siteSectionExcludeID = $sitemapINI->variable( 'SiteSettings', 'SiteSectionExcludeID' );
    $siteURLProtocol = $sitemapINI->variable( 'BCGoogleVideoSitemapSettings', 'SiteURLProtocol' );
    $siteFetchDepth = $sitemapINI->variable( 'BCGoogleVideoSitemapSettings', 'SiteFetchDepth' );
    $siteFetchLimit = $sitemapINI->variable( 'BCGoogleVideoSitemapSettings', 'SiteFetchLimit' );
    $objectAttributeVideoPlayerUrl = $sitemapINI->variable( 'BCGoogleVideoSitemapSettings', 'SiteVideoPlayerSWF' );

    $sitemapFilename = $sitemapINI->variable( 'BCGoogleVideoSitemapSettings', 'Filename' );
    $sitemapPath = $sitemapINI->variable( 'BCGoogleVideoSitemapSettings', 'Path' );
    $xmlDataFile = $sitemapPath . $sitemapFilename;

    $classFilterType = $sitemapINI->variable( 'Classes', 'ClassFilterType' );
    $classFilterArray = $sitemapINI->variable( 'Classes', 'ClassFilterArray' );

    $siteURL = $ini->variable( 'SiteSettings','SiteURL' );
}
else
{
    print_r( "Error: Missing settings! Check ini settings configuration ... \n" );
    return;
}

// Fetch the sitemap root node
$rootNode = eZContentObjectTreeNode::fetch( $sitemapRootNodeID );

// Test if the root node an object
if( !is_object( $rootNode ) )
{
    print_r( "Error: Invalid SitemapRootNodeID in settings! Check ini settings configuration ... \n" );
    return;
}

// Fetch the content tree content
$nodeArray = $rootNode->subTree( array( 'ClassFilterType' => $classFilterType,
                                        'ClassFilterArray' => $classFilterArray,
                                        'AttributeFilter' => array( array( 'section', '!=', $siteSectionExcludeID ) ),
                                        'Depth' => $siteFetchDepth,
                                        'Limit' => $siteFetchLimit ) );

// Define XML root nodes
$xmlRoot = "urlset";
$xmlNode = "url";
$xmlVideoNode = "video:video";

// Define XML child nodes
$xmlSubNodes = array( "loc", "lastmod", "changefreq", "priority" );
$xmlVideoSubNodes = array( "video:thumbnail_loc", "video:title", "video:description", "video:content_loc", "video:player_loc", "video:duration", "video:publication_date", "video:family_friendly", "video:live" );

// Create the DOM node
$dom = new DOMDocument( "1.0", "UTF-8" );

// Create DOM-Root ( urlset )
$root = $dom->createElement( $xmlRoot );
$root->setAttribute( "xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9" );
$root->setAttribute( "xmlns:video", "http://www.google.com/schemas/sitemap-video/1.1" );
$root = $dom->appendChild( $root );

// Generate XML content
foreach( $nodeArray as $subTreeNode )
{
   // Build url alias string
   $urlAlias = $siteURLProtocol . '://' . $siteURL . '/' . $subTreeNode->attribute( 'url_alias' );

   // Fetch object properties
   $object = $subTreeNode->object();
   $objectName = $subTreeNode->attribute('name');

   $depth = $subTreeNode->attribute( 'depth' );
   $modified = date( "c" , $object->attribute( 'modified' ) );
   $publishedDateTimeStampText = date( "c" , $object->attribute( 'published' ) );

   $objectDataMap = $subTreeNode->dataMap();
   $objectAttributeVideo = $objectDataMap['video'];
   $objectAttributeVideoContent = $objectAttributeVideo->content();

   if( $objectAttributeVideo->attribute( 'has_content' ) == true )
   {
       $objectAttributeVideoContentAttributes = $objectAttributeVideoContent->Attributes;

       if( $objectAttributeVideoContentAttributes['response'][0]['status'] != 'error' && isset( $objectAttributeVideoContentAttributes['thumb'] ) )
       {
           $objectAttributeVideoContentAttributeThumbnail = $objectAttributeVideoContentAttributes['thumb'];
           //$objectAttributeVideoContentAttributeDownload = $objectAttributeVideoContentAttributes['download'];
           $objectAttributeVideoContentAttributeDownloadMetaDetails = array_reverse( $objectAttributeVideoContentAttributes['response'][1]['conversions'] );
           $objectAttributeVideoContentAttributeDownloadMetaDetails = $objectAttributeVideoContentAttributeDownloadMetaDetails[0];
           $objectAttributeVideoContentAttributeDownload = $objectAttributeVideoContentAttributeDownloadMetaDetails['link'];
           $objectAttributeVideoContentAttributeDownload = $objectAttributeVideoContentAttributeDownloadMetaDetails['link']['protocol'] . '://' . $objectAttributeVideoContentAttributeDownloadMetaDetails['link']['address'] . $objectAttributeVideoContentAttributeDownloadMetaDetails['link']['path'];
           $objectAttributeVideoContentAttributeResponce = $objectAttributeVideoContentAttributes['response'];
           $objectAttributeVideoContentAttributeResponceVideoDuration = round( $objectAttributeVideoContentAttributeDownloadMetaDetails['duration'] );
           $objectAttributeDescriptionContentText = trim( strip_tags( $objectEmbededRelatedObjectDataMap['summary']->content()->attribute('output')->attribute('output_text') ) );

           // Create new url element
           $node = $dom->createElement( $xmlNode );

           // append to root node
           $node = $root->appendChild( $node );

           // create new url subnode
           $subNode = $dom->createElement( $xmlSubNodes[0] );
           $subNode = $node->appendChild( $subNode );

           // set text node with data
           $date = $dom->createTextNode( $urlAlias );
           $date = $subNode->appendChild( $date );

           // create modified subnode
           $subNode = $dom->createElement( $xmlSubNodes[1] );
           $subNode = $node->appendChild( $subNode );

           // set data
           $lastmod = $dom->createTextNode( $modified );
           $lastmod = $subNode->appendChild( $lastmod );

           // Create new video:video element
           $videoNode = $dom->createElement( $xmlVideoNode );

           // append to video node
           $videoNode = $node->appendChild( $videoNode );

           // Create new video:thumbnail_loc element
           $videoThumnail = $dom->createElement( $xmlVideoSubNodes[0] );

           // append to video node
           $videoThumnail = $videoNode->appendChild( $videoThumnail );

           // set text videoThumnail with data
           $videoThumbnailLoc = $dom->createTextNode( $objectAttributeVideoContentAttributeThumbnail );
           $videoThumbnailLoc = $videoThumnail->appendChild( $videoThumbnailLoc );

           // Create new video:title element
           $videoTitle = $dom->createElement( $xmlVideoSubNodes[1] );

           // append to video sub node
           $videoTitle = $videoNode->appendChild( $videoTitle );

           // set text videoTitle with data
           $videoTitleText = $dom->createTextNode( $objectName );
           $videoTitleText = $videoTitle->appendChild( $videoTitleText );

           // Create new video:description element
           $videoDescription = $dom->createElement( $xmlVideoSubNodes[2] );

           // append to video sub node
           $videoDescription = $videoNode->appendChild( $videoDescription );

           // set text videoDescription with data
           $videoTitleText = $dom->createTextNode( $objectAttributeDescriptionContentText );
           $videoTitleText = $videoDescription->appendChild( $videoTitleText );

           // Create new video:content_loc element
           $videoContentLoc = $dom->createElement( $xmlVideoSubNodes[3] );

           // append to video sub node
           $videoContentLoc = $videoNode->appendChild( $videoContentLoc );

           // set text videoContentLoc with data
           $videoContentLocText = $dom->createTextNode( $objectAttributeVideoContentAttributeDownload );
           $videoContentLocText = $videoContentLoc->appendChild( $videoContentLocText );

           if( $objectAttributeVideoPlayerUrl != '' )
           {
               // Create new video:player_loc element
               $videoPlayerLoc = $dom->createElement( $xmlVideoSubNodes[4] );
               $videoPlayerLoc->setAttribute( "allow_embed", "yes" );
               $videoPlayerLoc->setAttribute( "autoplay", "ap=1" );

               // append to video sub node
               $videoPlayerLoc = $videoNode->appendChild( $videoPlayerLoc );

               // set text videoPlayerLoc with data
               $videoPlayerLocText = $dom->createTextNode( $objectAttributeVideoPlayerUrl );
               $videoPlayerLocText = $videoPlayerLoc->appendChild( $videoPlayerLocText );
           }

           // Create new video:duration element
           $videoDuration = $dom->createElement( $xmlVideoSubNodes[5] );

           // append to video sub node
           $videoDuration = $videoNode->appendChild( $videoDuration );

           // set text videoDuration with data
           $videoDurationText = $dom->createTextNode( $objectAttributeVideoContentAttributeResponceVideoDuration );
           $videoDurationText = $videoDuration->appendChild( $videoDurationText );

           // Create new video:publication_date element
           $videoPublicationDate = $dom->createElement( $xmlVideoSubNodes[6] );

           // append to video sub node
           $videoPublicationDate = $videoNode->appendChild( $videoPublicationDate );

           // set text videoPublicationDate with data
           $videoPublicationDateText = $dom->createTextNode( $publishedDateTimeStampText );
           $videoPublicationDateText = $videoPublicationDate->appendChild( $videoPublicationDateText );

           // Create new video:family_friendly element
           $videoFamilyFriendly = $dom->createElement( $xmlVideoSubNodes[7] );

           // append to video sub node
           $videoFamilyFriendly = $videoNode->appendChild( $videoFamilyFriendly );

           // set text videoContentLoc with data
           $videoFamilyFriendlyText = $dom->createTextNode( 'yes' );
           $videoFamilyFriendlyText = $videoFamilyFriendly->appendChild( $videoFamilyFriendlyText );

           // Create new video:live element
           $videoLive = $dom->createElement( $xmlVideoSubNodes[8] );

           // append to video sub node
           $videoLive = $videoNode->appendChild( $videoLive );

           // set text videoContentLoc with data
           $videoLiveText = $dom->createTextNode( 'no' );
           $videoLiveText = $videoLive->appendChild( $videoLiveText );
       }
   }
}

// Create XML Sitemap storage directory if it does not already exist
if ( !file_exists( $sitemapPath ) && !is_dir( $sitemapPath ) )
{
    mkdir( $sitemapPath, 0775 );
}

// Write XML file to disk
$dom->save( $xmlDataFile );

// Alert user
if( !$isQuiet )
{
    $cli->output( "Video sitemap has been generated at: $xmlDataFile\n" );
}

?>