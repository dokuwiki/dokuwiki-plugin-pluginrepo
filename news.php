<?php

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
define('NOSESSION',true);
require_once(DOKU_INC.'inc/init.php');

/** @var helper_plugin_pluginrepo_newsletter $hlp */
$hlp = plugin_load('helper','pluginrepo_newsletter');
$hlp->execute();
