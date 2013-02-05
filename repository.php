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
    http_conditionalRequest(time());

ksort($opt);
$string = "pluginrepo";
foreach($opt as $key => $value) {
    $string .= "|$key:$value|";
}
$cache = new cache($string, '.xml');
if($cache->useCache(array('age'=>7200))) {
    $feed = $cache->retrieveCache();
} else {
    // create new feed
    $feed = getRepository($opt);

    // save cachefile
    $cache->storeCache($feed);
}
// finally deliver
print $feed;

// ---------------------------------------------------------------- //

/**
 * Get URL parameters and config options and return a initialized option array
 */
function parseOptions() {
    // no config options right now
    return  $_REQUEST;
}

/**
 * Return XML string with repository data, plugin relations (tags, similar, depends)
 * are only returned if $opt['plugins'] is used to return named plugins
 */
function getRepository($opt) {
    $hlp = new helper_plugin_pluginrepo();
    $plugins = $hlp->getPlugins($opt);

    $feed = '<?xml version="1.0" encoding="utf-8"?>';
    $feed .= '<repository>';
    foreach($plugins as $plugin) {
        $id = hsc($plugin['plugin']);
        $feed .= '<plugin>';
        $feed .= '<id>'.$id.'</id>';
        $feed .= '<dokulink>'.($plugin['type'] == 32 ? $id : 'plugin:'.$id).'</dokulink>';
        $feed .= '<popularity>'.$plugin['popularity'].'</popularity>';
        $feed .= '<name>'.hsc($plugin['name']).'</name>';
        $feed .= '<description>'.hsc($plugin['description']).'</description>';
        $feed .= '<author>'.hsc($plugin['author']).'</author>'; // mail not exposed as an anti-spam measure
        $feed .= '<type>';
        if ($plugin['type']) {
            $types = array();
            foreach($hlp->types as $k => $v){
                if($plugin['type'] & $k){
                    $types[] = $v;
                }
            }
            sort($types);
            $feed .= join(', ', $types);
        }
        $feed .= '</type>';

        $feed .= '<lastupdate>'.hsc(str_replace("'",'',$plugin['lastupdate'])).'</lastupdate>';
        if (strpos($plugin['compatible'],'devel') !== false) {
            $feed .= '<develonly>true</develonly>';
        }
        $feed .= '<compatible>';
        $compatibility = $hlp->cleanCompat($plugin['compatible']);
        foreach ($compatibility as $date => $info) {
            $feed .= '<release>'.$date.'</release>';
        }
        $feed .= '</compatible>';
        $feed .= '<securityissue>'.hsc($plugin['securityissue']).'</securityissue>';
        $feed .= '<securitywarning>';
        if (in_array($plugin['securitywarning'],$hlp->securitywarning)) {
            $feed .= $hlp->getLang('security_'.$plugin['securitywarning']);
        } else {
            $feed .= hsc($plugin['securitywarning']);
        }
        $feed .= '</securitywarning>';

        $feed .= '<tags>';
        $tags = $hlp->parsetags($plugin['tags']);
        foreach ($tags as $link) {
            $feed .= '<tag>'.hsc($link).'</tag>';
        }
        $feed .= '</tags>';

        if(empty($plugin['screenshot'])){
            $feed .= '<screenshoturl></screenshoturl>';
            $feed .= '<thumbnailurl></thumbnailurl>';
        }else{
            if(!preg_match('/^https?:\/\//', $plugin['screenshot'])){
                $feed .= '<screenshoturl>'.hsc(ml($plugin['screenshot'],array(),true,'&',true)).'</screenshoturl>';
            }else{
                $feed .= '<screenshoturl>'.hsc($plugin['screenshot']).'</screenshoturl>';
            }
            $feed .= '<thumbnailurl>'.hsc(ml($plugin['screenshot'], array('cache'=>'cache', 'w'=>120, 'h'=>70),true,'&',true)).'</thumbnailurl>';
        }

        $feed .= '<downloadurl>'.hsc($plugin['downloadurl']).'</downloadurl>';
        $feed .= '<sourcerepo>'.hsc($plugin['sourcerepo']).'</sourcerepo>';
        $feed .= '<bugtracker>'.hsc($plugin['bugtracker']).'</bugtracker>';
        $feed .= '<donationurl>'.hsc($plugin['donationurl']).'</donationurl>';

        $rel = $hlp->getPluginRelations($id);
        $feed .= '<relations>';

        $feed .= '<similar>';
        if ($rel['similar']) {
            foreach ($rel['similar'] as $link) {
                $feed .= '<id>'.hsc($link).'</id>';
            }
        }
        $feed .= '</similar>';

        $feed .= '<conflicts>';
        if ($rel['conflicts']) {
            foreach ($rel['conflicts'] as $link) {
                $feed .= '<id>'.hsc($link).'</id>';
            }
        }
        $feed .= '</conflicts>';

        $feed .= '<depends>';
        if ($rel['depends']) {
            foreach ($rel['depends'] as $link) {
                $feed .= '<id>'.hsc($link).'</id>';
            }
        }
        $feed .= '</depends>';

        $feed .= '</relations>';

        $feed .= '</plugin>';
    }
    $feed .= '</repository>';
    return $feed;
}

