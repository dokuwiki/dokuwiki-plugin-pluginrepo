<?php
/**
 * DokuWiki plugin/template/popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Håkan Sandell <hakan.sandell@home.se>
 */

class helper_plugin_pluginrepo extends DokuWiki_Plugin {

    var $localised = array();      // set to true by setupLocale() after loading language dependent strings
    var $lang = array();           // array to hold language dependent strings, best accessed via ->getLang()

    var $types = array(
                    1  => 'Syntax',
                    2  => 'Admin',
                    4  => 'Action',
                    8  => 'Render',
                    16 => 'Helper');

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
            // ignore comments
            $line = preg_replace('/(?<![&\\\\])#.*$/','',$line);
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
        return $data;
    }

    /**
     * getLang($id)
     * use this function to access plugin language strings
     * to try to minimise unnecessary loading of the strings when the plugin doesn't require them
     * e.g. when info plugin is querying plugins for information about themselves.
     *
     * @param   $id     id of the string to be retrieved
     * @return  string  string in appropriate language or english if not available
     */
    function getLang($langcode,$id) {
        if (!$this->localised[$langcode]) $this->setupLocale($langcode);

        return (isset($this->lang[$langcode][$id]) ? $this->lang[$langcode][$id] : '');
    }

    /**
     *  setupLocale()
     *  reads all the plugins language dependent strings into $this->lang
     *  this function is automatically called by getLang()
     */
    function setupLocale($langcode) {
        if ($this->localised[$langcode]) return;

        $path = DOKU_PLUGIN.$this->getPluginName().'/lang/';
        $lang = array();

        // don't include once, in case several plugin components require the same language file
        @include($path.'en/lang.php');
        if ($langcode != 'en') @include($path.$langcode.'/lang.php');

        $this->lang[$langcode] = $lang;
        $this->localised[$langcode] = true;
    }

    /**
     * Create database connection and return PDO object
     */
    function _getPluginsDB() {
        $db = null;
        try {
            // remember to use dblib:host=your_hostname;dbname=your_db;charset=UTF-8 for MSSQL ???
            $db = new PDO($this->getConf('db_name'), $this->getConf('db_user'), $this->getConf('db_pass'));
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // TODO: mysql_select_db
            //$db->exec('SET names utf8') only for mySQL
            // TODO behövs $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);  
        } catch(PDOException $e) {
            msg("Repository plugin: failed to connect to database (".$e->getMessage().")",-1);
            return null;
        }

        // trigger creation of tables if db empty
        try {
            $db->exec('SELECT 1 FROM plugin_depends');
        } catch(PDOException $e) {
            $this->_initPluginDB($db);
        }
        // TODO: koppla till conf(debug) eller alltid silent
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        return $db;
    }

    /**
     * Return array of plugins with some metadata
     */
    function getPlugins($filter=null) {
        $db = $this->_getPluginsDB();
        if (!$db) return;

        // TODO: don't filter security issues for plugin manager
        // TODO: add complex filters like field = null, name like a% Needed for gardening
        $plugins = $filter['plugins'];

        if ($plugins) {
            if (!is_array($plugins)) {
                $plugins = array($plugins);
            }
            $pluginsql = substr("AND plugin IN (".str_repeat("?,",count($plugins)),0,-1).")";
        } else {
            $type = (int)$filter['plugintype'];
            $tag  = strtolower(trim($filter['plugintag']));
        }

        $sort = strtolower(trim($filter['pluginsort']));
        $sortsql = $this->_getPluginsSortSql($sort);

        // TODO: remove A.
        // TODO: why funny char around `key`
        if ($this->types[$type]) {
            $stmt = $db->prepare("SELECT A.*, COUNT(C.value) as cnt
                                    FROM plugins A LEFT JOIN popularity C ON A.plugin = C.value and C.key = 'plugin'
                                   WHERE A.securityissue = ''
                                     AND (A.type & :type)
                                   GROUP BY A.plugin
                                $sortsql");
            $stmt->execute(array(':type' => $type));

        } elseif($tag) {
            $stmt = $db->prepare("SELECT A.*, COUNT(C.value) as cnt
                                    FROM plugin_tags B, plugins A LEFT JOIN popularity C ON A.plugin = C.value and C.key = 'plugin'
                                   WHERE A.securityissue = ''
                                     AND A.plugin = B.plugin
                                     AND B.tag = :tag
                                   GROUP BY A.plugin
                                $sortsql");
            $stmt->execute(array(':tag' => $tag));
        
        } else {
            $stmt = $db->prepare("SELECT A.*, COUNT(C.value) as cnt
                                    FROM plugins A LEFT JOIN popularity C ON A.plugin = C.value and C.key = 'plugin'
                                   WHERE A.securityissue = '' 
                              $pluginsql
                                   GROUP BY A.plugin
                                $sortsql");
            $stmt->execute($plugins);
        }

        $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $plugins;
    }

    function _getPluginsSortSql($sort) {
        if ($sort == 'a' || $sort == 'author') {
            $sortsql = 'ORDER BY A.author';
        } elseif ($sort == 'd' || $sort == 'lastupdate') {
            $sortsql = 'ORDER BY A.lastupdate DESC';
        } elseif ($sort == 't' || $sort == 'type') {
            $sortsql = 'ORDER BY A.type';
        } elseif ($sort == 'c' || $sort == 'popularity') {
            $sortsql = 'ORDER BY cnt DESC';
        } else {
            $sortsql = 'ORDER BY A.plugin';
        }
        return $sortsql;
    }
    
    /**
     * Return array of metadata about plugin
     */
    function getPluginRelations($id) {
        $db = $this->_getPluginsDB();
        if (!$db) return;

        $id = strtolower($id);
        $meta = array();
// TODO: handle template tags

        $stmt = $db->prepare('SELECT plugin FROM plugin_conflicts WHERE other = ?');
        $stmt->execute(array($id));
        foreach ($stmt as $row) {
            $conflicts .= ','.$row['plugin'];
        }
        
        $stmt = $db->prepare('SELECT other FROM plugin_conflicts WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach ($stmt as $row) {
            $conflicts .= ','.$row['other'];
        }
        if ($conflicts) {
            $meta['conflicts'] = substr($conflicts, 1);
        }

        $stmt = $db->prepare('SELECT plugin FROM plugin_similar WHERE other = ?');
        $stmt->execute(array($id));
        foreach ($stmt as $row) {
            $similar .= ','.$row['plugin'];
        }

        $stmt = $db->prepare('SELECT other FROM plugin_similar WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach ($stmt as $row) {
            $similar .= ','.$row['other'];
        }
        if ($similar) {
            $meta['similar'] = substr($similar, 1);
        }

        $stmt = $db->prepare('SELECT plugin FROM plugins WHERE author=(SELECT author FROM plugins WHERE plugin = ?)');
        $stmt->execute(array($id));
        foreach ($stmt as $row) {
            $sameauthor .= ','.$row['plugin'];
        }
        $sameauthor = str_replace(','.$id, '', $sameauthor);
        if ($sameauthor) {
            $meta['sameauthor'] = substr($sameauthor, 1);
        }

        return $meta;
    }

    /**
     * Return array of tags and their frequency in the repository
     */
    function getTags() {
        $db = $this->_getPluginsDB();
        if (!$db) return;

        $sql = "SELECT A.tag, COUNT(A.tag) as cnt
                  FROM plugin_tags as A, plugins as B
                 WHERE A.plugin = B.plugin
                   AND B.securityissue = ''
              GROUP BY tag";
        $stmt = $db->query($sql);
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);        

        // TODO: handle template & plugin, maybe by args

        return $tags;
    }

    /**
     * Clean comma separated list of plugins, return rendered as internallinks
     */
    // TODO: only used in "entry"
    function listplugins($string,&$R,$sep=', '){
        $plugins = explode(',',$string);
        $plugins = array_map('trim',$plugins);
        $plugins = array_map('strtolower',$plugins);
        $plugins = array_unique($plugins);
        $plugins = array_filter($plugins);
        sort($plugins);
        $out = array();
        foreach($plugins as $plugin){
            $out[] = $R->internallink(':plugin:'.$plugin,NULL,NULL,true);
        }
        return join($sep,$out);
    }

    /**
     * Convert comma separated list of tags to filterlinks
     */
    // TODO: only used in "entry"
    function listtags($string){
        $tags = $this->parsetags($string);
        $out = array();
        foreach($tags as $tag){
            $out[] = '<a href="'.wl($this->getConf('main'),array('plugintag'=>$tag)).
                     '" class="wikilink1" title="List all plugins with this tag">'.hsc($tag).'</a>';
        }
        return join(', ',$out);
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
    function listtype($type,$sep=', '){
        $types = array();
        foreach($this->types as $k => $v){
            if($type & $k){
                $types[] = '<a href="'.wl($this->getConf('main'),array('plugintype'=>$k)).
                           '" class="wikilink1" title="List all '.$v.' plugins">'.$v.'</a>';
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
								securityissue varchar(255) NOT NULL);');
		$db->exec('CREATE TABLE popularity (uid varchar(32) NOT NULL, key varchar(255) NOT NULL, value varchar(255) NOT NULL);');
    }
}
 




