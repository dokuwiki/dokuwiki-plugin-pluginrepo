<?php

/**
 * DokuWiki plugin/template/popularity data repository API
 *
 * The API repository.php is (only?) used for the Translation Tool, which just need all extensions
 * Filtering does not reduce collection significant for this application, so is skipped since October 2020.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Håkan Sandell <hakan.sandell@home.se>
 */

use dokuwiki\Cache\Cache;

if (!defined('DOKU_INC')) {
    define('DOKU_INC', __DIR__ . '/../../../');
}
require_once(DOKU_INC . 'inc/init.php');

require_once(DOKU_PLUGIN . 'pluginrepo/helper/repository.php');

//close session
session_write_close();

// check cache

header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
http_conditionalRequest(time());


$string = "pluginrepo";

$cache = new Cache($string, '.xml');
if ($cache->useCache(['age' => 7200])) {
    $feed = $cache->retrieveCache();
} else {
    // create new feed
    $feed = getRepository();

    // save cachefile
    $cache->storeCache($feed);
}
// finally deliver
echo $feed;

// ---------------------------------------------------------------- //

/**
 * Return XML string with repository data, plugin relations (tags, similar, depends)
 * are only returned if $opt['plugins'] is used to return named plugins
 *
 * @return string
 */
function getRepository()
{
    $hlp = new helper_plugin_pluginrepo_repository();
    $plugins = $hlp->getAllExtensions();

    $feed = '<?xml version="1.0" encoding="utf-8"?>';
    $feed .= '<repository>';
    foreach ($plugins as $plugin) {
        $id = hsc($plugin['plugin']);
        $feed .= '<plugin>';
        $feed .= '<id>' . $id . '</id>';
        $feed .= '<dokulink>' . ($plugin['type'] == 32 ? $id : 'plugin:' . $id) . '</dokulink>';
        $feed .= '<popularity>' . $plugin['popularity'] . '</popularity>';
        $feed .= '<name>' . hsc($plugin['name']) . '</name>';
        $feed .= '<description>' . hsc($plugin['description']) . '</description>';
        $feed .= '<author>' . hsc($plugin['author']) . '</author>'; // mail not exposed as an anti-spam measure
        $feed .= '<type>';
        if ($plugin['type']) {
            $types = [];
            foreach ($hlp->types as $k => $v) {
                if ($plugin['type'] & $k) {
                    $types[] = $v;
                }
            }
            sort($types);
            $feed .= implode(', ', $types);
        }
        $feed .= '</type>';

        $feed .= '<lastupdate>' . hsc(str_replace("'", '', $plugin['lastupdate'])) . '</lastupdate>';
        if (strpos($plugin['compatible'], 'devel') !== false) {
            $feed .= '<develonly>true</develonly>';
        }
        $feed .= '<compatible>';
        $compatibility = $hlp->cleanCompat($plugin['compatible']);
        foreach (array_keys($compatibility) as $date) {
            $feed .= '<release>' . $date . '</release>';
        }
        $feed .= '</compatible>';
        $feed .= '<securityissue>' . hsc($plugin['securityissue']) . '</securityissue>';
        $feed .= '<securitywarning>';
        if (in_array($plugin['securitywarning'], $hlp->securitywarning)) {
            $feed .= $hlp->getLang('security_' . $plugin['securitywarning']);
        } else {
            $feed .= hsc($plugin['securitywarning']);
        }
        $feed .= '</securitywarning>';

        $feed .= '<tags>';
        $tags = $hlp->parsetags($plugin['tags']);
        foreach ($tags as $link) {
            $feed .= '<tag>' . hsc($link) . '</tag>';
        }
        $feed .= '</tags>';

        if (empty($plugin['screenshot'])) {
            $feed .= '<screenshoturl></screenshoturl>';
            $feed .= '<thumbnailurl></thumbnailurl>';
        } else {
            if (!preg_match('/^https?:\/\//', $plugin['screenshot'])) {
                $feed .= '<screenshoturl>' . hsc(ml($plugin['screenshot'], [], true, '&', true)) . '</screenshoturl>';
            } else {
                $feed .= '<screenshoturl>' . hsc($plugin['screenshot']) . '</screenshoturl>';
            }
            $feed .= '<thumbnailurl>' . hsc(ml($plugin['screenshot'], ['cache' => 'cache', 'w' => 120, 'h' => 70], true, '&', true)) . '</thumbnailurl>';
        }

        $feed .= '<downloadurl>' . hsc($plugin['downloadurl']) . '</downloadurl>';
        $feed .= '<sourcerepo>' . hsc($plugin['sourcerepo']) . '</sourcerepo>';
        $feed .= '<bugtracker>' . hsc($plugin['bugtracker']) . '</bugtracker>';
        $feed .= '<donationurl>' . hsc($plugin['donationurl']) . '</donationurl>';

        $rel = $hlp->getPluginRelations($id);
        $feed .= '<relations>';

        $feed .= '<similar>';
        if ($rel['similar']) {
            foreach ($rel['similar'] as $link) {
                $feed .= '<id>' . hsc($link) . '</id>';
            }
        }
        $feed .= '</similar>';

        $feed .= '<conflicts>';
        if ($rel['conflicts']) {
            foreach ($rel['conflicts'] as $link) {
                $feed .= '<id>' . hsc($link) . '</id>';
            }
        }
        $feed .= '</conflicts>';

        $feed .= '<depends>';
        if ($rel['depends']) {
            foreach ($rel['depends'] as $link) {
                $feed .= '<id>' . hsc($link) . '</id>';
            }
        }
        $feed .= '</depends>';

        $feed .= '</relations>';

        $feed .= '</plugin>';
    }
    $feed .= '</repository>';
    return $feed;
}
