<?php
/**
 * DokuWiki plugin/template/popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <hakan.sandell@home.se>
 */

class helper_plugin_pluginrepo extends DokuWiki_Plugin {

    var $dokuReleases;             // array of DokuWiki releases (name & date)

    var $types = array(
                    1   => 'Syntax',
                    2   => 'Admin',
                    4   => 'Action',
                    8   => 'Render',
                    16  => 'Helper',
                    32  => 'Template',
                    64  => 'Remote',
                    128 => 'Auth');

    var $obsoleteTag = '!obsolete';
    var $bundled;
    var $securitywarning = array('informationleak','allowsscript','requirespatch','partlyhidden');

    function helper_plugin_pluginrepo() {
        $this->bundled = explode(',', $this->getConf('bundled'));
        $this->bundled = array_map('trim', $this->bundled);
        $this->bundled = array_filter($this->bundled);
    }

    /**
     * Parse syntax data block, return keyed array of values
     *
     *  You may use the # character to add comments to the block.
     *  Those will be ignored and will neither be displayed nor saved.
     *  If you need to enter # as data, escape it with a backslash (\#).
     *  If you need a backslash, escape it as well (\\)
     */
    function parseData($match){
        // get lines
        $lines = explode("\n",$match);
        array_pop($lines);
        array_shift($lines);

        // parse info
        $data = array();
        foreach ( $lines as $line ) {
            // ignore comments and bullet syntax
            $line = preg_replace('/(?<![&\\\\])#.*$/','',$line);
            $line = preg_replace('/^  \* /','',$line);
            $line = str_replace('\\#','#',$line);
            $line = trim($line);
            if(empty($line)) continue;
            list($key,$value) = preg_split('/\s*:\s*/',$line,2);
            $key = strtolower($key);
            if ($data[$key]){
                $data[$key] .= ' ' . trim($value);
            }else{
                $data[$key] = trim($value);
            }
        }
        // sqlite plugin compability (formerly used for templates)
        if ($data['lastupdate_dt']) $data['lastupdate'] = $data['lastupdate_dt'];
        if ($data['template_tags']) $data['tags'] = $data['template_tags'];
        if ($data['author_mail']) {
            list($mail,$name) = preg_split('/\s+/',$data['author_mail'],2);
            $data['author'] = $name;
            $data['email'] = $mail;
        }
        return $data;
    }

    /**
     * Create database connection and return PDO object
     * the config option 'db_name' must contain the
     * DataSourceName, which consists of the PDO driver name,
     * followed by a colon, followed by the PDO driver-specific connection syntax
     * see http://se2.php.net/manual/en/pdo.construct.php
     *
     * Example: 'mysql:dbname=testdb;host=127.0.0.1'
     *      or  'sqlite2:C:\DokuWikiStickNew\dokuwiki\repo.sqlite'
     */
    function _getPluginsDB() {
        global $conf;

        $db = null;
        try {
            $db = new PDO($this->getConf('db_name'), $this->getConf('db_user'), $this->getConf('db_pass'));
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
                // Running on mysql; do something mysql specific here
            }

        } catch(PDOException $e) {
            msg("Repository plugin: failed to connect to database (".$e->getMessage().")",-1);
            return null;
        }

        // trigger creation of tables if db empty
        try {
            $stmt = $db->prepare('SELECT 1 FROM plugin_depends LIMIT 1');
            $stmt->execute();
        } catch(PDOException $e) {
            $this->_initPluginDB($db);
        }

        if ($conf['allowdebug']) {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        } else {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        }
        return $db;
    }

    /**
     * Return array of plugins with some metadata
     * available filters passed as array:
     *   'plugins'    (array) returns data of named plugins
     *   'plugintype' (integer)
     *   'plugintag'  (string)
     *   'pluginsort' (string)
     *   'showall'    (yes/no) default/unset is 'no' and obsolete plugins and security issues are not returned
     *   'includetemplates' (yes/no) default/unset is 'no' and template data will not be returned
     */
    function getPlugins($filter=null) {
        $db = $this->_getPluginsDB();
        if (!$db) return;

        // return named plugins OR with certain tag/type
        $plugins = $filter['plugins'];
        $type = 0;
        if ($plugins) {
            if (!is_array($plugins)) {
                $plugins = array($plugins);
            }
            $pluginsql = substr("AND plugin IN (".str_repeat("?,",count($plugins)),0,-1).")";
            $filter['showissues'] = 'yes';
        } else {
            $type = (int)$filter['plugintype'];
            $tag  = strtolower(trim($filter['plugintag']));
        }

        $sort = strtolower(trim($filter['pluginsort']));
        $sortsql = $this->_getPluginsSortSql($sort);

        if ($filter['showall'] == 'yes') {
            $shown = "1";
        } else {
            $shown = "A.tags <> '".$this->obsoleteTag."' AND A.securityissue = ''";
        }
        if ($filter['includetemplates'] != 'yes') {
            $shown .= " AND A.type <> 32";
        }

        if ($tag) {
            if (!$this->types[$type]) {
                $type = 255;
            }
            $stmt = $db->prepare("      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugin_tags B, plugins A
                                         WHERE A.type = 32 AND $shown
                                           AND (A.type & :type)
                                           AND A.plugin = B.plugin
                                           AND B.tag = :tag
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugin_tags B, plugins A
                                         WHERE A.type <> 32 AND $shown
                                           AND (A.type & :type)
                                           AND A.plugin = B.plugin
                                           AND B.tag = :tag
                                $sortsql");
            $stmt->execute(array(':tag' => $tag, ':type' => $type));

        } elseif($this->types[$type]) {
            $stmt = $db->prepare("      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $shown
                                           AND (A.type & :type)
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $shown
                                           AND (A.type & :type)
                                 $sortsql");
            $stmt->execute(array(':type' => $type));

        } else {
            $stmt = $db->prepare("      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $shown
                                    $pluginsql
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $shown
                                    $pluginsql
                                 $sortsql");
            $stmt->execute($plugins);
        }

        $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $plugins;
    }

    /**
     * Translate sort keyword to sql clause
     */
    function _getPluginsSortSql($sort) {
        if ($sort{0} == '^') {
            $sortsql = ' DESC';
            $sort = substr($sort, 1);
        }
        if ($sort == 'a' || $sort == 'author') {
            $sortsql = 'ORDER BY author'.$sortsql;
        } elseif ($sort == 'd' || $sort == 'lastupdate') {
            $sortsql = 'ORDER BY lastupdate'.$sortsql;
        } elseif ($sort == 't' || $sort == 'type') {
            $sortsql = 'ORDER BY type'.$sortsql.', simplename';
        } elseif ($sort == 'v' || $sort == 'compatibility') {
            $sortsql = 'ORDER BY bestcompatible'.$sortsql.', simplename';
        } elseif ($sort == 'c' || $sort == 'popularity') {
            $sortsql = 'ORDER BY popularity'.$sortsql;
        } elseif ($sort == 'p' || $sort == 'plugin') {
            $sortsql = 'ORDER BY simplename'.$sortsql;
        } else {
            $sortsql = 'ORDER BY bestcompatible DESC, simplename'.$sortsql;
        }
        return $sortsql;
    }

    /**
     * Return array of metadata about plugin
     *   'conflicts'  array of plugin names
     *   'similar'    array of plugin names
     *   'depends'    array of plugin names
     *   'needed'     array of plugin names
     *   'sameauthor' array of plugin names
     */
    function getPluginRelations($id) {
        $db = $this->_getPluginsDB();
        if (!$db) return;

        $id = strtolower($id);
        $meta = array();

        $stmt = $db->prepare('SELECT plugin,other FROM plugin_conflicts WHERE plugin = ? OR other = ?');
        $stmt->execute(array($id,$id));
        foreach ($stmt as $row) {
            if($row['plugin'] == $id) $meta['conflicts'][] = $row['other'];
            elseif($row['other'] == $id) $meta['conflicts'][] = $row['plugin'];
        }


        $stmt = $db->prepare('SELECT plugin,other FROM plugin_similar WHERE plugin = ? OR other = ?');
        $stmt->execute(array($id,$id));
        foreach ($stmt as $row) {
            if($row['plugin'] == $id) $meta['similar'][] = $row['other'];
            elseif($row['other'] == $id) $meta['similar'][] = $row['plugin'];
        }


        $stmt = $db->prepare('SELECT other FROM plugin_depends WHERE plugin = ? ');
        $stmt->execute(array($id));
        foreach ($stmt as $row) {
            $meta['depends'][] = $row['other'];
        }


        $stmt = $db->prepare('SELECT plugin FROM plugin_depends WHERE other = ? ');
        $stmt->execute(array($id));
        foreach ($stmt as $row) {
            $meta['needed'][] = $row['plugin'];
        }


        $stmt = $db->prepare('SELECT plugin FROM plugins WHERE plugin <> ? AND email <> "" AND email=(SELECT email FROM plugins WHERE plugin = ?)');
        $stmt->execute(array($id,$id));
        foreach ($stmt as $row) {
            $meta['sameauthor'][] = $row['plugin'];
        }
        if(!empty($meta['conflicts'])) $meta['conflicts'] = array_unique($meta['conflicts']);
        if(!empty($meta['similar'])) $meta['similar'] = array_unique($meta['similar']);
        return $meta;
    }

    /**
     * Return array of tags and their frequency in the repository
     */
    function getTags($minlimit = 0,$filter) {
        $db = $this->_getPluginsDB();
        if (!$db) return;

        if ($filter['showall'] == 'yes') {
            $shown = "1";
        } else {
            $shown = "B.tags <> '".$this->obsoleteTag."' AND B.securityissue = ''";
        }
        if ($filter['plugintype'] == 32) {
            $shown .= ' AND B.type = 32';
        } elseif (!$filter['includetemplates']) {
            $shown .= ' AND B.type <> 32';
        }

        $stmt = $db->prepare("SELECT A.tag, COUNT(A.tag) as cnt
                                FROM plugin_tags as A, plugins as B
                               WHERE A.plugin = B.plugin
                                 AND $shown
                            GROUP BY tag
                              HAVING cnt >= :minlimit
                            ORDER BY cnt DESC");

        $stmt->bindParam(':minlimit', $minlimit, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $tags;
    }

    /**
     * Return number of installations for most popular plugin
     * besides the bundled ones
     *
     * @param string $type either 'plugins' or 'templates', '' shows all
     */
    function getMaxPopularity($type='') {
        $db = $this->_getPluginsDB();
        if (!$db) return;

        $sql = "SELECT popularity
                  FROM plugins
                 WHERE tags <> '".$this->obsoleteTag."' ";

        $sql .= str_repeat("AND plugin != ? ",count($this->bundled));

        if($type == 'plugins' || $type == 'plugin')   $sql .= "AND plugin NOT LIKE 'template:%'";
        if($type == 'templates' || $type == 'template') $sql .= "AND plugin LIKE 'template:%'";

        $sql .= "ORDER BY popularity DESC
                 LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($this->bundled);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $retval = $res[0]['popularity'];
        if(!$retval) $retval = 1;
        return $retval;
    }

    /**
     * Delete all information about plugin from repository database
     * (popularity data is left intact)
     */
    function deletePlugin($plugin){
        $db = $this->_getPluginsDB();
        if (!$db) return;

        $stmt = $db->prepare('DELETE FROM plugins          WHERE plugin = ?');
        $stmt->execute(array($plugin));

        $stmt = $db->prepare('DELETE FROM plugin_tags      WHERE plugin = ?');
        $stmt->execute(array($plugin));

        $stmt = $db->prepare('DELETE FROM plugin_similar   WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin,$plugin));

        $stmt = $db->prepare('DELETE FROM plugin_conflicts WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin,$plugin));

        $stmt = $db->prepare('DELETE FROM plugin_depends   WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin,$plugin));
    }

    /**
     * render internallink to plugin/template, templates identified by having namespace
     */
    function pluginlink(&$R,$plugin,$title=null) {
        if (!getNS($plugin)) {
            return $R->internallink(':plugin:'.$plugin,$title,null,true);
        } else {
            if (!$title) $title = noNS($plugin);
            return $R->internallink(':'.$plugin,$title,null,true);
        }
    }

    /**
     * Return array of supported DokuWiki releases
     * only releases mentioned in config are reported
     * 'newest' supported release at [0]
     */
    function cleanCompat($compatible) {
        if (!$this->dokuReleases) {
            $this->dokuReleases = array();
            $releases = explode(',', $this->getConf('releases'));
            $releases = array_map('trim',$releases);
            $releases = array_filter($releases);
            foreach ($releases as $release) {
                list($date,$name) = preg_split('/(\s+"\s*|")/',$release);
                $name = strtolower($name);
                $rel = array('date' => $date,
                             'name' => $name);
                $rel['label'] = ($name ? '"'.ucwords($name).'"' : '');
                $this->dokuReleases[$date] = $rel;
            }
        }

        preg_match_all('/([0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]\+?|[a-z A-Z]{4,}\+?)/', $compatible, $matches);
        $matches[0] = array_map('strtolower',$matches[0]);
        $matches[0] = array_map('trim',$matches[0]);
        $retval = array();
        $implicitCompatible = false;
        $dokuReleases = $this->dokuReleases;
        ksort($dokuReleases);
        foreach ($dokuReleases as $release) {
            if (in_array($release['date'].'+', $matches[0]) || in_array($release['name'].'+', $matches[0])) {
                $nextImplicitCompatible = true;
            }
            if ($nextImplicitCompatible || in_array($release['date'], $matches[0]) || in_array($release['name'], $matches[0]) || $implicitCompatible) {
                $retval[$release['date']]['label'] = $release['label'];
                $retval[$release['date']]['implicit'] = $implicitCompatible;
            }
            if ($nextImplicitCompatible) {
                $implicitCompatible = true;
            }
        }
        krsort($retval);
        return $retval;
    }

    function renderCompatibilityHelp($addInfolink=false) {
        $infolink = '<sup><a href="http://www.dokuwiki.org/extension_compatibility" title="'.$this->getLang('compatible_with_info').'">?</a></sup>';
        $infolink = $addInfolink ? $infolink : '';
        return sprintf($this->getLang('compatible_with'), $infolink);
    }

    /**
     * Clean list of plugins, return rendered as internallinks
     * input may be comma separated or array
     */
    function listplugins($plugins,&$R,$sep=', ') {
        if (!is_array($plugins)) $plugins = explode(',',$plugins);
        $plugins = array_map('trim',$plugins);
        $plugins = array_map('strtolower',$plugins);
        $plugins = array_unique($plugins);
        $plugins = array_filter($plugins);
        sort($plugins);
        $out = array();
        foreach($plugins as $plugin){
            $out[] = $this->pluginlink($R,$plugin);
        }
        return join($sep,$out);
    }

    /**
     * Convert comma separated list of tags to filterlinks
     */
    function listtags($string,$target,$sep=', ') {
        $tags = $this->parsetags($string);
        $out = array();
        foreach($tags as $tag){
            $out[] = '<a href="'.wl($target,array('plugintag'=>$tag)).'#extension__table" '.
                        'class="wikilink1" title="List all plugins with this tag">'.hsc($tag).'</a>';
        }
        return join($sep,$out);
    }

    /**
     * Clean comma separated list of tags, return as sorted array
     */
    function parsetags($string){
        $tags = preg_split('/[;,\s]/',$string);
        $tags = array_map('strtolower',$tags);
        $tags = array_unique($tags);
        $tags = array_filter($tags);
        sort($tags);
        return $tags;
    }

    /**
     * Convert $type (int) to list of filterlinks
     */
    function listtype($type,$target,$sep=', '){
        $types = array();
        foreach($this->types as $k => $v){
            if($type & $k){
                $types[] = '<a href="'.wl($target,array('plugintype'=>$k)).'#extension__table" '.
                              'class="wikilink1" title="List all '.$v.' plugins">'.$v.'</a>';
            }
        }
        sort($types);
        return join($sep,$types);
    }

    /**
     * Convert plugin type name (comma sep. string) to (int)
     */
    function parsetype($types){
        $type = 0;
        foreach($this->types as $k => $v){
            if(preg_match('/'.$v.'/i',$types)) $type += $k;
        }
        return $type;
    }

    /**
     * Create tables for repository
     */
    function _initPluginDB($db) {
        msg("Repository plugin: data tables created for plugin repository",-1);
        $db->exec('CREATE TABLE plugin_conflicts (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_depends (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_similar (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_tags (plugin varchar(50) NOT NULL, tag varchar(255) NOT NULL);');
        $db->exec('CREATE TABLE plugins (plugin varchar(50) PRIMARY KEY NOT NULL, name varchar(255) default NULL,
                                description varchar(255) default NULL, author varchar(255) default NULL, email varchar(255) default NULL,
                                compatible varchar(255) default NULL, lastupdate date default NULL, downloadurl varchar(255) default NULL,
                                bugtracker varchar(255) default NULL, sourcerepo varchar(255) default NULL, donationurl varchar(255) default NULL, type int(11) NOT NULL default 0,
                                screenshot varchar(255) default NULL, tags varchar(255) default NULL, securitywarning varchar(255) default NULL, securityissue varchar(255) NOT NULL,
                                bestcompatible varchar(50) default NULL, popularity int default 0);');
    }
}

