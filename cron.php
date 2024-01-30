<?php
/**
 * Calculate the popularity per plugin/template from the popularity database tables
 * Check for version and base discrepancies and update the 'devel:badextensions' page
 */
$TIMEFRAME = 60 * 60 * 24 * 365 * 2; // in seconds

$TIME = time() - $TIMEFRAME;

if (!defined('DOKU_INC')) {
    define('DOKU_INC', __DIR__ . '/../../../');
}
define('NOSESSION', true);
require_once(DOKU_INC . 'inc/init.php');

/** @var helper_plugin_pluginrepo_repository $hlp */
$hlp = plugin_load('helper', 'pluginrepo_repository');
$db  = $hlp->getPluginsDB();
if (!$db) {
    die('failed to connect to DB');
}


// update all plugins
$sql = "SELECT count(*) as cnt, A.value as plugin
          FROM popularity A, popularity B
         WHERE A.uid = B.uid
           AND A.`key` = 'plugin'
           AND B.`key` = 'now'
           AND B.value > $TIME
      GROUP BY A.value";
$stmt = $db->prepare($sql);
$stmt->execute();
$updt = $db->prepare("UPDATE plugins SET popularity = :pop WHERE plugin = :name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $updt->execute([':pop' => $row['cnt'], ':name' => $row['plugin']]);
}

// update all templates
$sql = "SELECT count(*) as cnt, A.value as template
          FROM popularity A, popularity B
         WHERE A.uid = B.uid
           AND A.`key` = 'conf_template'
           AND B.`key` = 'now'
           AND B.value > $TIME
      GROUP BY A.value";
$stmt = $db->prepare($sql);
$stmt->execute();
$updt = $db->prepare("UPDATE plugins SET popularity = :pop WHERE plugin = :name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $updt->execute([':pop' => $row['cnt'], ':name' => 'template:' . $row['template']]);
}

// create info on bad extension versions
/** @var helper_plugin_pluginrepo_version $version */
$version = plugin_load('helper', 'pluginrepo_version');
$version->execute();
