<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_pluginrepo extends DokuWiki_Syntax_Plugin {

    var $db = null;

    var $types = array(
                    1  => 'Syntax',
                    2  => 'Admin',
                    4  => 'Action',
                    8  => 'Render',
                    16 => 'Helper');

    var $bundled = array(
        'plugin','config','popularity','info','usermanager','acl','revert',
        'importoldchangelog','importoldindex'
    );

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Andreas Gohr',
            'email'  => 'andi@splitbrain.org',
            'date'   => '2010-01-09',
            'name'   => 'Plugin Repository',
            'desc'   => 'Helps organizing the plugin repository',
            'url'    => 'http://www.dokuwiki.org/plugin:pluginrepo',
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 155;
    }


    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *plugin *-+\n.*?\n----+',$mode,'plugin_pluginrepo');
        $this->Lexer->addSpecialPattern('~~pluginrepo~~',$mode,'plugin_pluginrepo');
    }


    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        if($match == '~~pluginrepo~~'){
            return null;
        }else{
            // get lines
            $lines = explode("\n",$match);
            array_pop($lines);
            array_shift($lines);

            // parse info
            $data = array();
            foreach ( $lines as $line ) {
                //ignore comments
                $line = preg_replace('/(?<!&)#.*$/','',$line);
                $line = trim($line);
                if(empty($line)) continue;
                $line = preg_split('/\s*:\s*/',$line,2);
                $data[strtolower($line[0])] = $line[1];
            }
            return $data;
        }
    }

    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        global $ID;

        switch ($format){
            case 'xhtml':
                if(is_array($data)) $this->_load(noNS($ID),$renderer,$data);
                if(is_null($data))  $this->_display($renderer);
                return true;
                break;
            case 'metadata':
                if(is_array($data) && (substr_count($ID,':') == 1)){
                    $this->_save($data,noNS($ID),$renderer->meta['title']);
                }
                return true;
                break;
            default:
                return false;
        }
    }

    function _display(&$R){
        $this->_dbconnect();

        // get maximum pop
        $sql = "SELECT COUNT(uid) as cnt
                  FROM popularity
                 WHERE `key` = 'plugin'";
        foreach($this->bundled as $bnd){
            $sql .= " AND `value` != '$bnd'";
        }
        $sql .= "GROUP BY `value`
                 ORDER BY cnt DESC
                 LIMIT 1";
        $res = mysql_query($sql,$this->db);
        $row = mysql_fetch_assoc($res);
        $popmax = $row['cnt'];
        if(!$popmax) $popmax = 1;
        mysql_free_result($res);

        // get maximum pop
        $sql = "SELECT COUNT(DISTINCT uid) as cnt
                  FROM popularity
                 WHERE `key` = 'plugin'
                   AND `value` = 'popularity'";
        $res = mysql_query($sql,$this->db);
        $row = mysql_fetch_assoc($res);
        $allcnt = $row['cnt'];
        if(!$allcnt) $allcnt = 1;
        mysql_free_result($res);



        $type = (int) $_REQUEST['plugintype'];
        $tag  = trim($_REQUEST['plugintag']);
        $sort = trim($_REQUEST['pluginsort']);
        if($sort == 'a'){
            $sortsql = 'ORDER BY A.author';
        }elseif($sort == 'd'){
            $sortsql = 'ORDER BY A.lastupdate DESC';
        }elseif($sort == 't'){
            $sortsql = 'ORDER BY A.type';
        }elseif($sort == 'c'){
            $sortsql = 'ORDER BY cnt DESC';
        }else{
            $sortsql = 'ORDER BY A.plugin';
        }


        if($this->types[$type]){
            $sql = "SELECT A.*, COUNT(C.value) as cnt
                      FROM plugins A LEFT JOIN popularity C ON A.plugin = C.value and C.`key` = 'plugin'
                     WHERE (A.type & $type)
                       AND A.securityissue = ''
                  GROUP BY A.plugin
                  $sortsql";
            $header = 'Available '.$this->types[$type].' Plugins';

            $linkopt = "plugintype=$type,";
        }elseif($tag){
            $sql = "SELECT A.*, COUNT(C.value) as cnt
                      FROM plugin_tags B, plugins A LEFT JOIN popularity C ON A.plugin = C.value and C.`key` = 'plugin'
                     WHERE A.plugin = B.plugin
                       AND A.securityissue = ''
                       AND B.tag = '".addslashes($tag)."'
                  GROUP BY A.plugin
                  $sortsql";
            $header = 'Available Plugins tagged with "'.hsc($tag).'"';
            $linkopt = "plugintag=".rawurlencode($tag).',';
        }else{
            $sql = "SELECT A.*, COUNT(C.value) as cnt
                      FROM plugins A LEFT JOIN popularity C ON A.plugin = C.value and C.`key` = 'plugin'
                     WHERE A.securityissue = ''
                  GROUP BY A.plugin
                  $sortsql";
            $header = 'Available Plugins';
            $linkopt = '';
        }

        $res = mysql_query($sql,$this->db);
        $num = mysql_num_rows($res);
        $header .= ' ('.$num.')';

        $R->header($header, 2, null);
        $R->section_open(2);

        $R->doc .= '<div id="pluginrepo__repo">';

        $R->doc .= '<div class="repo_info"><p>This is the list of all plugin currently available
                    for DokuWiki. You may filter the list by tags from the cloud to the left or
                    by type:</p>
                    <ul>
                        <li>';
                            $R->internallink($this->getConf('main'),'All');
        $R->doc .= '    </li>
                        <li>';
        $R->doc .=          $this->_listtype(1+2+4+8+16,'</li><li>');
        $R->doc .= '    </li>';
        $R->doc .= '</ul>';
        $R->doc .= '</div>';

        $R->doc .= '<div class="repo_cloud">';
        $this->_tagcloud($R);
        $R->doc .= '</div>';

        $R->doc .= '<div class="clearer"></div>';
        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=p').'" title="Sort by name">Plugin</a></th>
                        <th>Description</th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=a').'" title="Sort by author">Author</a></th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=t').'" title="Sort by type">Type</a></th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=d').'" title="Sort by date">Last Update</a></th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=c').'" title="Sort by popularity">Popularity</a></th>
                    </tr>';
        while ($row = mysql_fetch_assoc($res)) {
            $link = $R->internallink(':plugin:'.$row['plugin'],null,null,true);
            if(strpos($link,'class="wikilink2"')){
                $this->_delete($row['plugin']);
                continue;
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td>';
            $R->doc .= $link;
            $R->doc .= '</td>';
            $R->doc .= '<td>';
            $R->doc .= '<strong>'.hsc($row['name']).'</strong><br />';
            $R->doc .= hsc($row['description']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->emaillink($row['email'],$row['author']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= $this->_listtype($row['type']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= hsc($row['lastupdate']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            if(in_array($row['plugin'],$this->bundled)){
                $R->doc .= '<i>bundled</i>';
            }else{
                $R->doc .= '<div class="prog-border" title="'.$row['cnt'].'/'.$allcnt.'"><div class="prog-bar" style="width: '.sprintf(100*$row['cnt']/$popmax).'%;"></div></div>';
            }
            $R->doc .= '</td>';

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
        $R->doc .= '</div>';
        $R->section_close();
        mysql_free_result($res);
    }


    function _load($id, &$R, $data){
        global $ACT;
        $this->_dbconnect();

        $R->doc .= '<div id="pluginrepo__plugin"><div>';


        $R->doc .= '<p><strong>'.noNS($id).' plugin</strong> by ';
        $R->emaillink($data['email'],$data['author']);
        $R->doc .= '<br />'.hsc($data['description']).'</p>';

        $R->doc .= '<p>';
        if(preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])){
            $R->doc .= '<span class="lastupd">Last updated on <em>'.
                       $data['lastupdate'].'</em>.</span> ';
        }
        $type = $this->_parsetype($data['type']);
        if($type){
            $R->doc .= '<span class="type">Provides <em>'.$this->_listtype($type).
                       '</em>.</span>';
        }

        $R->doc .= '<br />';
        if($data['compatible']){
            $R->doc .= '<span class="compatible">Compatible with <em>DokuWiki '.
                       hsc($data['compatible']).'</em>.</span>';
        }else{
            $R->doc .= '<span class="compatible">No compatibility info given!</span>';
        }
        $R->doc .= '</p></div>';

        $R->doc .= '<p>';
        $sql = "SELECT plugin
                  FROM plugin_conflicts
                 WHERE other = '".addslashes($id)."'";
        $res = mysql_query($sql,$this->db);
        while ($row = mysql_fetch_assoc($res)) {
            $data['conflicts'] .= ','.$row['plugin'];
        }
        if($data['conflicts']){
            $R->doc .= '<span class="conflicts">Conflicts with <em>';
            $R->doc .= $this->_listplugins($data['conflicts'],$R);
            $R->doc .= '</em>!</span><br />';
        }
        if($data['depends']){
            $R->doc .= '<span class="depends">Requires <em>';
            $R->doc .= $this->_listplugins($data['depends'],$R);
            $R->doc .= '</em>.</span><br />';
        }
        $sql = "SELECT plugin
                  FROM plugin_similar
                 WHERE other = '".addslashes($id)."'";
        $res = mysql_query($sql,$this->db);
        while ($row = mysql_fetch_assoc($res)) {
            $data['similar'] .= ','.$row['plugin'];
        }
        if($data['similar']){
            $R->doc .= '<span class="similar">Similar to <em>';
            $R->doc .= $this->_listplugins($data['similar'],$R);
            $R->doc .= '</em>.</span><br />';
        }
        $R->doc .= '</p>';

        $R->doc .= '<p>';
        if($data['tags']){
            $R->doc .= '<span class="tags">Tagged with <em>';
            $R->doc .= $this->_listtags($data['tags']);
            $R->doc .= '</em>.</span><br />';
        }
        $R->doc .= '</p>';


        if($data['securityissue']){
            $R->doc .= '<p class="security">';
            $R->doc .= '<b>The following security issue was reported for this plugin:</b><br /><br />';
            $R->doc .= '<i>'.hsc($data['securityissue']).'</i><br /><br />';
            $R->doc .= 'It is not recommended to use this plugin until this issue was fixed. Plugin authors should read the ';
            $R->doc .= $R->internallink('devel:security','plugin security guidelines',NULL,true);
            $R->doc .= '.</p>';
        }

        $R->doc .= '</div>';


        // add tabs
        $R->doc .= '<ul id="pluginrepo__foldout">';
        if($data['downloadurl']) $R->doc .= '<li><a class="download" href="'.hsc($data['downloadurl']).'">Download the Plugin</a></li>';
        if($data['bugtracker'])  $R->doc .= '<li><a class="bugs" href="'.hsc($data['bugtracker']).'">Bugs / Feature Wishes</a></li>';
        if($data['sourcerepo'])  $R->doc .= '<li><a class="repo" href="'.hsc($data['sourcerepo']).'">Source Repository</a></li>';
        if($data['donationurl']) $R->doc .= '<li><a class="donate" href="'.hsc($data['donationurl']).'">Thank the Author</a></li>';
        $R->doc .= '</ul>';

        return;
    }

    function _listplugins($string,&$R){
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
        return join(', ',$out);
    }

    function _listtype($type,$sep=', '){
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

    function _parsetags($string){
        $tags = preg_split('/[;,\s]/',$string);
        $tags = array_map('strtolower',$tags);
        $tags = array_unique($tags);
        $tags = array_filter($tags);
        sort($tags);
        return $tags;
    }

    function _listtags($string){
        $tags = $this->_parsetags($string);
        $out = array();
        foreach($tags as $tag){
            $out[] = '<a href="'.wl($this->getConf('main'),array('plugintag'=>$tag)).
                     '" class="wikilink1" title="List all plugins with this tag">'.hsc($tag).'</a>';
        }
        return join(', ',$out);
    }

    function _tagcloud(&$R){
        $sql = "SELECT A.tag, COUNT(A.tag) as cnt
                  FROM plugin_tags as A, plugins as B
                 WHERE A.plugin = B.plugin
                   AND B.securityissue = ''
              GROUP BY tag";
        $res = mysql_query($sql,$this->db);

        $min  = 99999999;
        $max  = 0;
        $tags = array();
        while ($row = mysql_fetch_assoc($res)) {
            if($row['cnt'] == 1) continue; // skip single tags
            $tags[$row['tag']] = $row['cnt'];
            if($row['cnt'] > $max) $max = $row['cnt'];
            if($row['cnt'] < $min) $min = $row['cnt'];
        }

        $this->_cloud_weight($tags,$min,$max,5);

        ksort($tags);
        foreach($tags as $tag => $size){
            $R->doc .= '<a href="'.wl($this->getConf('main'),array('plugintag'=>$tag)).
                       '" class="wikilink1 cl'.$size.'" '.
                       'title="List all plugins with this tag">'.hsc($tag).'</a> ';
        }
    }

    function _cloud_weight(&$tags,$min,$max,$levels){
        // calculate tresholds
        $tresholds = array();
        for($i=0; $i<=$levels; $i++){
            $tresholds[$i] = pow($max - $min + 1, $i/$levels) + $min - 1;
        }

        // assign weights
        foreach($tags as $tag => $cnt){
            foreach($tresholds as $tresh => $val){
                if($cnt <= $val){
                    $tags[$tag] = $tresh;
                    break;
                }
                $tags[$tag] = $levels;
            }
        }
    }

    function _parsetype($types){
        $type = 0;
        foreach($this->types as $k => $v){
            if(preg_match('/'.$v.'/i',$types)) $type += $k;
        }
        return $type;
    }

    /**
     * Takes the parsed data and saves it
     */
    function _save($data,$id,$name){
        $this->_dbconnect();

        if(!$name) $name = $id;
        $id   = addslashes($id);
        $name = addslashes($name);
        $data = array_map('addslashes',$data);
        if(!preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])){
             $data['lastupdate'] = 'NULL';
        }else{
            $data['lastupdate'] = "'".$data['lastupdate']."'";
        }

        $type = $this->_parsetype($data['type']);

        $sql = "REPLACE INTO plugins
                    SET plugin      = '$id',
                        name        = '$name',
                        description = '".$data['description']."',
                        author      = '".$data['author']."',
                        email       = LOWER('".$data['email']."'),
                        compatible  = '".$data['compatible']."',
                        lastupdate  = ".$data['lastupdate'].",
                        securityissue = '".$data['securityissue']."',
                        downloadurl = '".$data['downloadurl']."',
                        bugtracker  = '".$data['bugtracker']."',
                        sourcerepo  = '".$data['sourcerepo']."',
                        donationurl = '".$data['sourcerepo']."',
                        type        = $type";
        mysql_query($sql,$this->db);

        $tags = $this->_parsetags($data['tags']);
        $sql  = "DELETE FROM plugin_tags WHERE plugin = '$id'";
        mysql_query($sql,$this->db);
        foreach($tags as $tag){
            $tag = addslashes($tag);
            $sql  = "INSERT IGNORE INTO plugin_tags SET plugin = '$id', tag = LOWER('$tag')";
            mysql_query($sql,$this->db);
        }

        $deps = explode(',',$data['depends']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $sql  = "DELETE FROM plugin_depends WHERE plugin = '$id'";
        mysql_query($sql,$this->db);
        foreach($deps as $dep){
            $dep = addslashes($dep);
            $sql  = "INSERT IGNORE INTO plugin_depends SET plugin = '$id', other = LOWER('$dep')";
            mysql_query($sql,$this->db);
        }

        $deps = explode(',',$data['conflicts']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $sql  = "DELETE FROM plugin_conflicts WHERE plugin = '$id'";
        mysql_query($sql,$this->db);
        foreach($deps as $dep){
            $dep = addslashes($dep);
            $sql  = "INSERT IGNORE INTO plugin_conflicts SET plugin = '$id', other = LOWER('$dep')";
            mysql_query($sql,$this->db);
        }

        $deps = explode(',',$data['similar']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $sql  = "DELETE FROM plugin_similar WHERE plugin = '$id'";
        mysql_query($sql,$this->db);
        foreach($deps as $dep){
            $dep = addslashes($dep);
            $sql  = "INSERT IGNORE INTO plugin_similar SET plugin = '$id', other = LOWER('$dep')";
            mysql_query($sql,$this->db);
        }
    }

    function _delete($plugin){
        $plugin = addslashes($plugin);
        $sql = "DELETE FROM plugins WHERE plugin = '$plugin'";
        mysql_query($sql,$this->db);

        $sql = "DELETE FROM plugin_tags WHERE plugin = '$plugin'";
        mysql_query($sql,$this->db);

        $sql = "DELETE FROM plugin_similar WHERE plugin = '$plugin' OR other = '$plugin'";
        mysql_query($sql,$this->db);

        $sql = "DELETE FROM plugin_conflicts WHERE plugin = '$plugin' OR other = '$plugin'";
        mysql_query($sql,$this->db);

        $sql = "DELETE FROM plugin_depends WHERE plugin = '$plugin' OR other = '$plugin'";
        mysql_query($sql,$this->db);
    }

    function _dbconnect(){
        $this->db = mysql_connect($this->getConf('db_host'),
                                  $this->getConf('db_user'),
                                  $this->getConf('db_pass'));
        if(!$this->db) die('Could not connect: ' . mysql_error());

        mysql_select_db($this->getConf('db_name'), $this->db) || die ('Can\'t use db: ' . mysql_error());

        mysql_query('set names utf8',$this->db);
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
