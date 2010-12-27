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

/**
 * TODO
 */
function parseOptions() {
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
        $feed .= '<plugin>';
        $id = hsc($plugin['A.plugin']);
        $feed .= '<id>'.$id.'</id>';
        if ($plugin['A.type'] == 32) {
            $feed .= '<link><a href="'.DOKU_URL.'doku.php?id=template:'.$id.'" title="template:'.$id.'">'.$id.'</a></link>';
        } else {
            $feed .= '<link><a href="'.DOKU_URL.'doku.php?id=plugin:'.$id.'" title="plugin:'.$id.'">'.$id.'</a></link>';
        }
        $feed .= '<name>'.hsc($plugin['A.name']).'</name>';
        $feed .= '<description>'.hsc($plugin['A.description']).'</description>';
        $feed .= '<author>'.hsc($plugin['A.author']).'</author>'; // mail not exposed as an anti-spam measure
        $feed .= '<type>';
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
        $feed .= '</type>';

        $feed .= '<lastupdate>'.hsc(str_replace("'",'',$plugin['A.lastupdate'])).'</lastupdate>';
        if (strpos($plugin['A.compatible'],'devel') !== false) {
            $feed .= '<develonly>true</develonly>';
        }
        $feed .= '<compatible>';
        $compatibility = $hlp->cleanCompat($plugin['A.compatible']);
        foreach ($compatibility as $release => $value) {
            if ($value) {
                $feed .= '<release>'.$release.'</release>';
            }
        }
        $feed .= '</compatible>';
        $feed .= '<securityissue>'.hsc($plugin['A.securityissue']).'</securityissue>';
        $feed .= '<securitywarning>';
        if (in_array($plugin['A.securitywarning'],$hlp->securitywarning)) {
            $feed .= $hlp->getLang($lang,'security_'.$plugin['A.securitywarning']);
        } else {
            $feed .= hsc($plugin['A.securitywarning']);
        }
        $feed .= '</securitywarning>';

        $feed .= '<tags>';
        $tags = $hlp->parsetags($plugin['A.tags']);
        foreach ($tags as $link) {
            $feed .= '<tag>'.hsc($link).'</tag>';
        }
        $feed .= '</tags>';

        $feed .= '<downloadurl>'.hsc($plugin['A.downloadurl']).'</downloadurl>';
        $feed .= '<bugtracker>'.hsc($plugin['A.bugtracker']).'</bugtracker>';
        $feed .= '<donationurl>'.hsc($plugin['A.donationurl']).'</donationurl>';

        if ($opt['plugins']) {
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
        }

        $feed .= '</plugin>';
    }
    $feed .= '</repository>';
    return $feed;
}

