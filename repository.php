<?php
/**
 * DokuWiki plugin/template/popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Håkan Sandell <hakan.sandell@home.se>
 */


if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
require_once(DOKU_INC.'inc/init.php');

require_once(DOKU_PLUGIN.'pluginrepo/helper.php');

//close session
session_write_close();

// get params
$opt = parseOptions();

// check cache

header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
// TODO: add caching
    http_conditionalRequest(time());

// create new feed
$feed = getRepository($opt);

// save cachefile
//$cache->storeCache($feed);

// finally deliver
print $feed;

// ---------------------------------------------------------------- //

function parseOptions() {
    return  $_REQUEST;
}

function getRepository($opt) {
    $hlp = new helper_plugin_pluginrepo();
    $plugins = $hlp->getPlugins($opt);

    $feed = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
    $feed .= "<repository>\n";
    foreach($plugins as $plugin) {
        $feed .= "  <plugin>\n";
        $id = hsc($plugin['A.plugin']);
        $feed .= "    <id>$id</id>\n";
        $feed .= "    <link><a href=\"".DOKU_URL."doku.php?id=plugin:$id\" title=\"plugin:$id\">$id</a></link>\n";
        $feed .= "    <name>".hsc($plugin['A.name'])."</name>\n";
        $feed .= "    <description>".hsc($plugin['A.description'])."</description>\n";
        $feed .= "    <type>";
        if ($plugin['A.type']) {
            $types = array();
            foreach($hlp->types as $k => $v){
                if($plugin['A.type'] & $k){
                    $types[] = $v;
                }
            }
            sort($types);
            $feed .= join(', ', $types);
        }
        $feed .= "</type>\n";
        // TODO: add tags, similar, conflicts        
        $feed .= "    <lastupdate>".hsc($plugin['A.lastupdate'])."</lastupdate>\n";
        $feed .= "    <compatible>".hsc($plugin['A.compatible'])."</compatible>\n";
        $feed .= "    <securityissue>".hsc($plugin['A.securityissue'])."</securityissue>\n";
        $feed .= "    <author>".hsc($plugin['A.author'])."</author>\n"; // mail not exposed as an anti-spam measure
        $feed .= "    <downloadurl>".hsc($plugin['A.downloadurl'])."</downloadurl>\n";
        $feed .= "    <bugtracker>".hsc($plugin['A.bugtracker'])."</bugtracker>\n";
        $feed .= "    <donationurl>".hsc($plugin['A.donationurl'])."</donationurl>\n";
        $feed .= "  </plugin>\n";
    }
    $feed .= "</repository>\n";
    return $feed;
}





