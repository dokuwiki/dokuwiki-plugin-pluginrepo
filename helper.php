<?php
/**
 * DokuWiki plugin/template/popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <hakan.sandell@home.se>
 */

class helper_plugin_pluginrepo extends DokuWiki_Plugin {

    public $dokuReleases; // array of DokuWiki releases (name & date)

    public $types = array(
        1   => 'Syntax',
        2   => 'Admin',
        4   => 'Action',
        8   => 'Render',
        16  => 'Helper',
        32  => 'Template',
        64  => 'Remote',
        128 => 'Auth'
    );


    public $obsoleteTag = '!obsolete';
    public $bundled;
    public $securitywarning = array('informationleak', 'allowsscript', 'requirespatch', 'partlyhidden');

    public function helper_plugin_pluginrepo() {
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
    public function parseData($match) {
        // get lines
        $lines = explode("\n", $match);
        array_pop($lines);
        array_shift($lines);

        // parse info
        $data = array();
        foreach($lines as $line) {
            // ignore comments and bullet syntax
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = preg_replace('/^  \* /', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if(empty($line)) continue;
            list($key, $value) = preg_split('/\s*:\s*/', $line, 2);
            $key = strtolower($key);
            if($data[$key]) {
                $data[$key] .= ' '.trim($value);
            } else {
                $data[$key] = trim($value);
            }
        }
        // sqlite plugin compability (formerly used for templates)
        if($data['lastupdate_dt']) $data['lastupdate'] = $data['lastupdate_dt'];
        if($data['template_tags']) $data['tags'] = $data['template_tags'];
        if($data['author_mail']) {
            list($mail, $name) = preg_split('/\s+/', $data['author_mail'], 2);
            $data['author'] = $name;
            $data['email']  = $mail;
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
    public function _getPluginsDB() {
        global $conf;
        /** @var $db PDO */
        $db = null;
        try {
            $db = new PDO($this->getConf('db_name'), $this->getConf('db_user'), $this->getConf('db_pass'));
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
                // Running on mysql; do something mysql specific here
            }

        } catch(PDOException $e) {
            msg("Repository plugin: failed to connect to database (".$e->getMessage().")", -1);
            return null;
        }

        // trigger creation of tables if db empty
        try {
            $stmt = $db->prepare('SELECT 1 FROM plugin_depends LIMIT 1');
            $stmt->execute();
        } catch(PDOException $e) {
            $this->_initPluginDB($db);
        }

        if($conf['allowdebug']) {
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
    public function getPlugins($filter = null) {
        $db = $this->_getPluginsDB();
        if(!$db) return array();

        // return named plugins OR with certain tag/type
        $plugins = $filter['plugins'];
        $type    = 0;
        $tag     = '';
        if($plugins) {
            if(!is_array($plugins)) {
                $plugins = array($plugins);
            }
            $pluginsql            = substr("AND plugin IN (".str_repeat("?,", count($plugins)), 0, -1).")";
            $filter['showissues'] = 'yes';
        } else {
            $type = (int) $filter['plugintype'];
            $tag  = strtolower(trim($filter['plugintag']));
        }

        $sort    = strtolower(trim($filter['pluginsort']));
        $sortsql = $this->_getPluginsSortSql($sort);

        if($filter['showall'] == 'yes') {
            $shown = "1";
        } else {
            $shown = "A.tags <> '".$this->obsoleteTag."' AND A.securityissue = ''";
        }
        if($filter['includetemplates'] != 'yes') {
            $shown .= " AND A.type <> 32";
        }

        if($tag) {
            if(!$this->types[$type]) {
                $type = 255;
            }
            $stmt = $db->prepare(
                "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
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
                                $sortsql"
            );
            $stmt->execute(array(':tag' => $tag, ':type' => $type));

        } elseif($this->types[$type]) {
            $stmt = $db->prepare(
                "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $shown
                                           AND (A.type & :type)
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $shown
                                           AND (A.type & :type)
                                 $sortsql"
            );
            $stmt->execute(array(':type' => $type));

        } else {
            $stmt = $db->prepare(
                "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $shown
                                    $pluginsql
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $shown
                                    $pluginsql
                                 $sortsql"
            );
            $stmt->execute($plugins);
        }

        $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $plugins;
    }

    /**
     * Gives all available info on plugins. Used for API
     *
     * tags, similar, depends, conflicts have newline separated lists
     *
     * @param array  $names    names of wanted extensions (use template: prefix)
     * @param array  $emailids md5s of emails of wanted extension authors
     * @param int    $type     ANDed types you want, 0 for all
     * @param array  $tags     show only extensions with these tags
     * @param string $order    order by this column
     * @param int    $limit    number of items
     * @param string $fulltext search term for full text search
     *
     * @return array
     * @throws Exception
     */
    public function getFilteredPlugins(
        $names = array(),
        $emailids = array(),
        $type = 0,
        $tags = array(),
        $order = '',
        $limit = 0,
        $fulltext = ''
    ) {
        $fulltext = trim($fulltext);

        $maxpop = $this->getMaxPopularity();

        // default to all extensions
        if($type == 0) {
            foreach(array_keys($this->types) as $t) $type += $t;
        }

        // cleanup $order
        $order = preg_replace('/[^a-z]+/', '', $order);
        if($order == 'popularity') $order .= ' DESC';
        if($order == 'lastupdate') $order .= ' DESC';
        if($order == '') {
            if($fulltext) {
                $order = 'score DESC';
            } else {
                $order = 'plugin';
            }
        }

        // limit
        $limit = (int) $limit;
        if($limit) {
            $limit = "LIMIT $limit";
        } else {
            if($fulltext) {
                $limit = 'LIMIT 50';
            } else {
                $limit = '';
            }
        }

        // name filter
        $namefilter = '';
        $nameparams = array();
        if($names) {
            $count = 0;
            foreach($names as $name) {
                $nameparams[':name'.$count++] = $name;
            }

            $namefilter = 'AND A.plugin IN ('.join(',', array_keys($nameparams)).')';
        }

        // email filter
        $emailfilter = '';
        $emailparams = array();
        if($emailids) {
            $count = 0;
            foreach($emailids as $email) {
                $emailparams[':email'.$count++] = $email;
            }

            $emailfilter = 'AND MD5(LOWER(A.email)) IN ('.join(',', array_keys($emailparams)).')';
        }

        // tag filter
        $tagfilter = '';
        $tagparams = array();
        if($tags) {
            $count = 0;
            foreach($tags as $tag) {
                $tagparams[':tag'.$count++] = $tag;
            }

            $tagfilter = 'AND B.tag IN ('.join(',', array_keys($tagparams)).')';
        }

        // fulltext search
        $fulltextwhere  = '';
        $fulltextfilter = '';
        $fulltextparams = array();
        if($fulltext) {
            $fulltextwhere  = 'MATCH (A.plugin, A.name, A.description, A.author, A.tags)
                                 AGAINST (:fulltext WITH QUERY EXPANSION) AS score,';
            $fulltextfilter = 'AND MATCH (A.plugin, A.name, A.description, A.author, A.tags)
                                 AGAINST (:fulltext WITH QUERY EXPANSION)';
            $fulltextparams = array(':fulltext' => $fulltext);
        }

        $sql = "SELECT A.*,
                       A.popularity/:maxpop as popularity,
                       MD5(LOWER(A.email)) as emailid,
                       $fulltextwhere
                       GROUP_CONCAT(DISTINCT B.tag ORDER BY B.tag SEPARATOR '\n') as tags,
                       GROUP_CONCAT(DISTINCT C.other ORDER BY C.other SEPARATOR '\n') as similar,
                       GROUP_CONCAT(DISTINCT D.other ORDER BY D.other SEPARATOR '\n') as depends,
                       GROUP_CONCAT(DISTINCT E.other ORDER BY E.other SEPARATOR '\n') as conflicts
                  FROM plugins A
             LEFT JOIN plugin_tags B
                    ON A.plugin = B.plugin
             LEFT JOIN plugin_similar C
                    ON A.plugin = C.plugin
             LEFT JOIN plugin_depends D
                    ON A.plugin = D.plugin
             LEFT JOIN plugin_conflicts E
                    ON A.plugin = E.plugin

                 WHERE (A.type & :type)
                       $namefilter
                       $tagfilter
                       $emailfilter
                       $fulltextfilter
              GROUP BY A.plugin
              ORDER BY $order
                       $limit";

        $db = $this->_getPluginsDB();
        if(!$db) throw new Exception('Cannot connect to database');

        $parameters = array_merge(
            array(':type' => $type, ':maxpop' => $maxpop),
            $nameparams,
            $tagparams,
            $emailparams,
            $fulltextparams
        );

        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
        $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // add and cleanup few more fields
        $cnt = count($plugins);
        for($i = 0; $i < $cnt; $i++) {
            if(in_array($plugins[$i]['plugin'], $this->bundled)) {
                $plugins[$i]['bundled'] = true;
            } else {
                $plugins[$i]['bundled'] = false;
            }
            if(!$plugins[$i]['screenshot']) {
                $plugins[$i]['screenshoturl'] = null;
                $plugins[$i]['thumbnailurl']  = null;
            } else {
                $plugins[$i]['screenshoturl'] = ml($plugins[$i]['screenshot'], '', true, '&', true);
                $plugins[$i]['thumbnailurl']  = ml($plugins[$i]['screenshot'], array('w' => 120, 'h' => 70), true, '&', true);
            }
            unset($plugins[$i]['screenshot']);
            unset($plugins[$i]['email']); // no spam

            $plugins[$i]['depends']   = array_filter(explode("\n", $plugins[$i]['depends']));
            $plugins[$i]['similar']   = array_filter(explode("\n", $plugins[$i]['similar']));
            $plugins[$i]['conflicts'] = array_filter(explode("\n", $plugins[$i]['conflicts']));
            $plugins[$i]['tags']      = array_filter(explode("\n", $plugins[$i]['tags']));

            $plugins[$i]['compatible'] = $this->cleanCompat($plugins[$i]['compatible']);
            $plugins[$i]['types']      = $this->listtypes($plugins[$i]['type']);

            ksort($plugins[$i]);
        }

        return $plugins;
    }

    /**
     * Translate sort keyword to sql clause
     */
    private function _getPluginsSortSql($sort) {
        $sortsql = '';
        if($sort{0} == '^') {
            $sortsql = ' DESC';
            $sort    = substr($sort, 1);
        }
        if($sort == 'a' || $sort == 'author') {
            $sortsql = 'ORDER BY author'.$sortsql;
        } elseif($sort == 'd' || $sort == 'lastupdate') {
            $sortsql = 'ORDER BY lastupdate'.$sortsql;
        } elseif($sort == 't' || $sort == 'type') {
            $sortsql = 'ORDER BY type'.$sortsql.', simplename';
        } elseif($sort == 'v' || $sort == 'compatibility') {
            $sortsql = 'ORDER BY bestcompatible'.$sortsql.', simplename';
        } elseif($sort == 'c' || $sort == 'popularity') {
            $sortsql = 'ORDER BY popularity'.$sortsql;
        } elseif($sort == 'p' || $sort == 'plugin') {
            $sortsql = 'ORDER BY simplename'.$sortsql;
        } else {
            $sortsql = 'ORDER BY bestcompatible DESC, simplename'.$sortsql;
        }
        return $sortsql;
    }

    /**
     * @param string $id of plugin
     * @return array of metadata about plugin:
     *   'conflicts'  array of plugin names
     *   'similar'    array of plugin names
     *   'depends'    array of plugin names
     *   'needed'     array of plugin names
     *   'sameauthor' array of plugin names
     */
    public function getPluginRelations($id) {
        $db = $this->_getPluginsDB();
        if(!$db) return array();

        $id   = strtolower($id);
        $meta = array();

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare('SELECT plugin,other FROM plugin_conflicts WHERE plugin = ? OR other = ?');
        $stmt->execute(array($id, $id));
        foreach($stmt as $row) {
            if($row['plugin'] == $id) $meta['conflicts'][] = $row['other'];
            elseif($row['other'] == $id) $meta['conflicts'][] = $row['plugin'];
        }

        $stmt = $db->prepare('SELECT plugin,other FROM plugin_similar WHERE plugin = ? OR other = ?');
        $stmt->execute(array($id, $id));
        foreach($stmt as $row) {
            if($row['plugin'] == $id) $meta['similar'][] = $row['other'];
            elseif($row['other'] == $id) $meta['similar'][] = $row['plugin'];
        }

        $stmt = $db->prepare('SELECT other FROM plugin_depends WHERE plugin = ? ');
        $stmt->execute(array($id));
        foreach($stmt as $row) {
            $meta['depends'][] = $row['other'];
        }

        $stmt = $db->prepare('SELECT plugin FROM plugin_depends WHERE other = ? ');
        $stmt->execute(array($id));
        foreach($stmt as $row) {
            $meta['needed'][] = $row['plugin'];
        }

        $stmt = $db->prepare('SELECT plugin FROM plugins WHERE plugin <> ? AND email <> "" AND email=(SELECT email FROM plugins WHERE plugin = ?)');
        $stmt->execute(array($id, $id));
        foreach($stmt as $row) {
            $meta['sameauthor'][] = $row['plugin'];
        }
        if(!empty($meta['conflicts'])) $meta['conflicts'] = array_unique($meta['conflicts']);
        if(!empty($meta['similar'])) $meta['similar'] = array_unique($meta['similar']);
        return $meta;
    }

    /**
     * Return array of tags and their frequency in the repository
     */
    public function getTags($minlimit = 0, $filter) {
        $db = $this->_getPluginsDB();
        if(!$db) return array();

        if($filter['showall'] == 'yes') {
            $shown = "1";
        } else {
            $shown = "B.tags <> '".$this->obsoleteTag."' AND B.securityissue = ''";
        }
        if($filter['plugintype'] == 32) {
            $shown .= ' AND B.type = 32';
        } elseif(!$filter['includetemplates']) {
            $shown .= ' AND B.type <> 32';
        }

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare(
            "SELECT A.tag, COUNT(A.tag) as cnt
                                FROM plugin_tags as A, plugins as B
                               WHERE A.plugin = B.plugin
                                 AND $shown
                            GROUP BY tag
                              HAVING cnt >= :minlimit
                            ORDER BY cnt DESC"
        );

        $stmt->bindParam(':minlimit', $minlimit, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $tags;
    }

    /**
     * Return number of installations for most popular plugin
     * besides the bundled ones
     * Otherwise 1 is return, to prevent dividing by zero. (and it correspondence with the usage of the author self)
     *
     * @param string $type either 'plugins' or 'templates', '' shows all
     * @return int
     */
    public function getMaxPopularity($type = '') {
        $db = $this->_getPluginsDB();
        if(!$db) return 1;

        $sql = "SELECT popularity
                  FROM plugins
                 WHERE tags <> '".$this->obsoleteTag."' ";

        $sql .= str_repeat("AND plugin != ? ", count($this->bundled));

        if($type == 'plugins' || $type == 'plugin') $sql .= "AND plugin NOT LIKE 'template:%'";
        if($type == 'templates' || $type == 'template') $sql .= "AND plugin LIKE 'template:%'";

        $sql .= "ORDER BY popularity DESC
                 LIMIT 1";

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare($sql);
        $stmt->execute($this->bundled);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $retval = $res[0]['popularity'];
        if(!$retval) $retval = 1;
        return (int) $retval;
    }

    /**
     * Delete all information about plugin from repository database
     * (popularity data is left intact)
     */
    public function deletePlugin($plugin) {
        $db = $this->_getPluginsDB();
        if(!$db) return;

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare('DELETE FROM plugins          WHERE plugin = ?');
        $stmt->execute(array($plugin));

        $stmt = $db->prepare('DELETE FROM plugin_tags      WHERE plugin = ?');
        $stmt->execute(array($plugin));

        $stmt = $db->prepare('DELETE FROM plugin_similar   WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin, $plugin));

        $stmt = $db->prepare('DELETE FROM plugin_conflicts WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin, $plugin));

        $stmt = $db->prepare('DELETE FROM plugin_depends   WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin, $plugin));
    }

    /**
     * render internallink to plugin/template, templates identified by having namespace
     *
     * @param $R Doku_Renderer_xhtml
     * @param $plugin string pluginname
     * @param $title string Title of plugin link
     * @return string rendered internallink
     */
    public function pluginlink(&$R, $plugin, $title = null) {
        if(!getNS($plugin)) {
            return $R->internallink(':plugin:'.$plugin, $title, null, true);
        } else {
            if(!$title) $title = noNS($plugin);
            return $R->internallink(':'.$plugin, $title, null, true);
        }
    }

    /**
     * Return array of supported DokuWiki releases
     * only releases mentioned in config are reported
     * 'newest' supported release at [0]
     */
    public function cleanCompat($compatible) {
        if(!$this->dokuReleases) {
            $this->dokuReleases = array();
            $releases           = explode(',', $this->getConf('releases'));
            $releases           = array_map('trim', $releases);
            $releases           = array_filter($releases);
            foreach($releases as $release) {
                list($date, $name) = preg_split('/(\s+"\s*|")/', $release);
                $name                      = strtolower($name);
                $rel                       = array(
                    'date' => $date,
                    'name' => $name
                );
                $rel['label']              = ($name ? '"'.ucwords($name).'"' : '');
                $this->dokuReleases[$date] = $rel;
            }
        }

        preg_match_all('/([0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]\+?|[a-z A-Z]{4,}\+?)/', $compatible, $matches);
        $matches[0]         = array_map('strtolower', $matches[0]);
        $matches[0]         = array_map('trim', $matches[0]);
        $retval             = array();
        $implicitCompatible = false;
        $nextImplicitCompatible = false;
        $dokuReleases       = $this->dokuReleases;
        ksort($dokuReleases);
        foreach($dokuReleases as $release) {
            if(in_array($release['date'].'+', $matches[0]) || in_array($release['name'].'+', $matches[0])) {
                $nextImplicitCompatible = true;
            }
            if($nextImplicitCompatible || in_array($release['date'], $matches[0]) || in_array($release['name'], $matches[0]) || $implicitCompatible) {
                $retval[$release['date']]['label']    = $release['label'];
                $retval[$release['date']]['implicit'] = $implicitCompatible;
            }
            if($nextImplicitCompatible) {
                $implicitCompatible = true;
            }
        }
        krsort($retval);
        return $retval;
    }

    /**
     * @param bool $addInfolink
     * @return string rendered
     */
    public function renderCompatibilityHelp($addInfolink = false) {
        $infolink = '<sup><a href="http://www.dokuwiki.org/extension_compatibility" title="'.$this->getLang('compatible_with_info').'">?</a></sup>';
        $infolink = $addInfolink ? $infolink : '';
        return sprintf($this->getLang('compatible_with'), $infolink);
    }

    /**
     * Clean list of plugins, return rendered as internallinks
     * input may be comma separated or array
     */
    public function listplugins($plugins, &$R, $sep = ', ') {
        if(!is_array($plugins)) $plugins = explode(',', $plugins);
        $plugins = array_map('trim', $plugins);
        $plugins = array_map('strtolower', $plugins);
        $plugins = array_unique($plugins);
        $plugins = array_filter($plugins);
        sort($plugins);
        $out = array();
        foreach($plugins as $plugin) {
            $out[] = $this->pluginlink($R, $plugin);
        }
        return join($sep, $out);
    }

    /**
     * Convert comma separated list of tags to filterlinks
     */
    public function listtags($string, $target, $sep = ', ') {
        $tags = $this->parsetags($string);
        $out  = array();
        foreach($tags as $tag) {
            $out[] = '<a href="'.wl($target, array('plugintag' => $tag)).'#extension__table" '.
                'class="wikilink1" title="List all plugins with this tag">'.hsc($tag).'</a>';
        }
        return join($sep, $out);
    }

    /**
     * Clean comma separated list of tags, return as sorted array
     */
    public function parsetags($string) {
        $tags = preg_split('/[;,\s]/', $string);
        $tags = array_map('strtolower', $tags);
        $tags = array_unique($tags);
        $tags = array_filter($tags);
        sort($tags);
        return $tags;
    }

    /**
     * Convert $type (int) to list of filterlinks
     */
    public function listtype($type, $target, $sep = ', ') {
        $types = array();
        foreach($this->types as $k => $v) {
            if($type & $k) {
                $types[] = '<a href="'.wl($target, array('plugintype' => $k)).'#extension__table" '.
                    'class="wikilink1" title="List all '.$v.' plugins">'.$v.'</a>';
            }
        }
        sort($types);
        return join($sep, $types);
    }

    /**
     * Convert $type (int) to array of names
     */
    public function listtypes($type) {
        $types = array();
        foreach($this->types as $k => $v) {
            if($type & $k) $types[] = $v;
        }
        sort($types);
        return $types;
    }

    /**
     * Convert plugin type name (comma sep. string) to (int)
     */
    public function parsetype($types) {
        $type = 0;
        foreach($this->types as $k => $v) {
            if(preg_match('/'.$v.'/i', $types)) $type += $k;
        }
        return $type;
    }

    /**
     * Create tables for repository
     *
     * @param $db PDO
     */
    private function _initPluginDB($db) {
        msg("Repository plugin: data tables created for plugin repository", -1);
        $db->exec('CREATE TABLE plugin_conflicts (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_depends (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_similar (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_tags (plugin varchar(50) NOT NULL, tag varchar(255) NOT NULL);');
        $db->exec(
            'CREATE TABLE plugins (plugin varchar(50) PRIMARY KEY NOT NULL, name varchar(255) default NULL,
                                   description varchar(255) default NULL, author varchar(255) default NULL, email varchar(255) default NULL,
                                   compatible varchar(255) default NULL, lastupdate date default NULL, downloadurl varchar(255) default NULL,
                                   bugtracker varchar(255) default NULL, sourcerepo varchar(255) default NULL, donationurl varchar(255) default NULL, type int(11) NOT NULL default 0,
                                   screenshot varchar(255) default NULL, tags varchar(255) default NULL, securitywarning varchar(255) default NULL, securityissue varchar(255) NOT NULL,
                                   bestcompatible varchar(50) default NULL, popularity int default 0);'
        );
    }
}

