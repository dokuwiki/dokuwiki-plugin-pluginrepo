<?php

if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../');
require_once(DOKU_INC.'inc/init.php');

require_once(DOKU_PLUGIN.'pluginrepo/helper.php');

//close session
session_write_close();

/** @var helper_plugin_pluginrepo $REPO */
$REPO = plugin_load('helper', 'pluginrepo');

// query the repository
$extensions = $REPO->getFilteredPlugins(
    $INPUT->arr('ext'),
    $INPUT->arr('mail'),
    $INPUT->int('type'),
    $INPUT->arr('tag'),
    $INPUT->str('order'),
    $INPUT->int('limit'),
    $INPUT->str('q')
);

header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('X-Robots-Tag: noindex');

if($INPUT->str('cmd') == 'ping') {
    header('Content-Type: text/plain; charset=utf-8');
    echo '1';
} else {
    switch($INPUT->str('fmt')) {
        case 'debug':
            header('Content-Type: text/plain; charset=utf-8');
            print_r($extensions);
            break;
        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            require('A2Xml.php');
            $xml = xml_encode((object) $extensions, "hash");
            echo $xml;
            break;
        case 'yaml':
            header('Content-Type: text/yaml');
            require('Spyc.php');
            echo Spyc::YAMLDump($extensions, false, 0);
            break;
        case 'php':
            header('Content-Type: application/vnd.php.serialized');
            echo serialize($extensions);
            break;
        default:
            $json = new JSON();
            $data = $json->encode($extensions);
            $cb   = $INPUT->str('cb');
            $cb   = preg_replace('/\W+/', '', $cb);
            if($cb) {
                header('Content-Type: text/javascript');
                echo "$cb($data);";
            } else {
                header('Content-Type: application/json');
                echo $data;
            }
    }
}
