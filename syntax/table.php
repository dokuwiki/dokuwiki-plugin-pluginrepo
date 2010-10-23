<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if (!defined('DOKU_LF')) define('DOKU_LF', "\n");

class syntax_plugin_pluginrepo_table extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $hlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_pluginrepo_table(){
        $this->hlp =& plugin_load('helper', 'pluginrepo');
        if(!$this->hlp) msg('Loading the pluginrepo helper failed. Make sure the pluginrepo plugin is installed.',-1);
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
        $this->Lexer->addSpecialPattern('~~pluginrepo~~',$mode,'plugin_pluginrepo_table');
        $this->Lexer->addSpecialPattern('----+ *pluginrepo *-+\n.*?\n----+',$mode,'plugin_pluginrepo_table');
    }


    /**
     * Handle the match - parse the data
     *
     * This parsing is shared between the multiple different output/control
     * syntaxes
     */
    function handle($match, $state, $pos, &$handler){
        return $this->hlp->parseData($match);
    }

    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        global $ID;

        if($format == 'xhtml') {
            return $this->_showData(noNS($ID),$renderer,$data);
        }
        return false;
    }

    /**
     * Override getLang to be able to select language by namespace
     */
    function getLang($langcode,$id) {
        return $this->hlp->getLang($langcode,$id);
    }

    /**
     * Output table of plugins with filter and navigation
     */
    function _showData($id, &$R, $data){
        $R->info['cache'] = false;

        $R->header('Search for plugins', 2, null);
        $R->section_open(2);

        $R->doc .= '<div id="pluginrepo__repo">';
        $R->doc .= '  <div class="repo_infos">';
        $R->doc .= '    <div class="repo_info">';
        $this->_showMainSearch($id, &$R, $data);
        $R->doc .= '    </div>'.DOKU_LF;

        $R->doc .= '    <div class="repo_info2">';
        $this->_showPluginTypeFilter($id, &$R, $data);
        $R->doc .= '    </div>'.DOKU_LF;

        $R->doc .= '    <div class="repo_info3">';
        $this->_showPluginNews($id, &$R, $data);
        $R->doc .= '    </div>'.DOKU_LF;
        $R->doc .= '  </div>';

        $R->doc .= '  <div class="repo_cloud">';
        $R->doc .= '    <h3>Filter plugins by tag</h3>';
        $this->_tagcloud($R, $data);
        $R->doc .= '  </div>'.DOKU_LF;

        $R->doc .= '</div>';
        $R->doc .= '<div class="clearer"></div>';
        $R->section_close();

        $this->_showPluginTable($id, &$R, $data);
    }

    /**
     * TODO
     */
    function _showMainSearch($id, &$R, $data){
        if ($data['textsearch']) {
            $R->doc .= '<p>'.hsc($data['textsearch']).'</p>';
        } else {
            $R->doc .= '<p>There are many ways to search among available DokuWiki plugins.
                        You may filter the list by tags from the cloud to the left or
                        by type. Of cause you can also use the search box.</p>';
        }
        return;
        // TODO: enable search box
    	$R->doc .= '<div id="searchform_plugin">';
		$R->doc .= '<form action="'.wl().'" accept-charset="utf-8" class="search" id="dw__search"><div class="no">'."\n";
		$R->doc .= '<input type="hidden" name="do" value="search" />'."\n";
		$R->doc .= 'Search <input type="text" ';
		if($ACT == 'search') $renderer->doc .= 'value="'.htmlspecialchars($_REQUEST['id']).'" ';
		if(!$autocomplete) $renderer->doc .= 'autocomplete="off" ';
		$R->doc .= 'id="qsearch__in" accesskey="f" name="id" class="edit" title="[ALT+F]" />'."\n";
		$R->doc .= '<input type="submit" value="'.$lang['btn_search'].'" class="button" title="'.$lang['btn_search'].'" />'."\n";
		if($ajax) $renderer->doc .= '<div id="qsearch__out" class="ajax_qsearch JSpopup"></div>'."\n";
		$R->doc .= '</div></form>'."\n";
		$R->doc .= '</div>'."\n";
        $R->doc .= '<div class="clearer"></div>';

		$R->doc .= '</div>';
    }

    /**
     * TODO
     */
    function _showPluginTypeFilter($id, &$R, $data){
        $R->doc .= '<h3>Filter plugins by type</h3>';
        $R->doc .= 'DokuWiki features different plugin types.';
        return;
        $R->doc .= '<ul>
                        <li><div class="li">';
        $R->doc .=          $this->hlp->listtype(1)." plugins extend DokuWiki's basic syntax.";
        $R->doc .= '    </div></li>';
        $R->doc .= '    <li><div class="li">';
        $R->doc .=          $this->hlp->listtype(4)." plugins can be used to extend or replace many aspects of DokuWiki's core operations...";
        $R->doc .= '    </div></li>';
        $R->doc .= '    <li><div class="li">';
        $R->doc .=          $this->hlp->listtype(2)." plugins can provide administration functionality for DokuWiki...";
        $R->doc .= '    </div></li>';
        $R->doc .= '    <li><div class="li">';
        $R->doc .=          $this->hlp->listtype(16)." plugins can be used to provide functionality to many other plugins...";
        $R->doc .= '    </div></li>';
        $R->doc .= '    <li><div class="li">';
        $R->doc .=          $this->hlp->listtype(8)." plugins allow to create new export modes and to replace the standard DokuWiki xhtml renderer";
        $R->doc .= '    </div></li>';
        $R->doc .= '</ul>';
    }

    /**
     * TODO
     */
    function _showPluginNews($id, &$R, $data){
        $R->doc .= '<h3>Most popular</h3>';
        return;
//        $R->doc .= $this->_listplugins($mostpopular,$R);
		// dummy
		$sql2 = "SELECT plugin, description
                  FROM plugins 
              ORDER BY author 
			 LIMIT 3";
        $res2 = sqlite_query($sql2,$this->db);
        $R->doc .= '<ul>';
        while ($row = sqlite_fetch_array($res2, SQLITE_ASSOC)) {
            $R->doc .= '    <li><div class="li">';
            $R->doc .= '<div class="repo_infoplugintitle">';
			$R->internallink(':plugin:'.$row['plugin'], ucfirst($row['plugin']). ' plugin');
			$R->doc .= '</div> '. hsc($row['description']);
            $R->doc .= '    </div></li>';
            $latest .= $row['plugin'].',';
		}
        $R->doc .= '</ul>';


        $R->doc .= '<h3>Recently updated</h3>';
		// latest
		$sql2 = "SELECT plugin, description
                  FROM plugins 
              ORDER BY lastupdate 
			DESC LIMIT 2";
        $res2 = sqlite_query($sql2,$this->db);
        $R->doc .= '<ul>';
        while ($row = sqlite_fetch_array($res2, SQLITE_ASSOC)) {
            $R->doc .= '    <li><div class="li">';
            $R->doc .= '<div class="repo_infoplugintitle">';
			$R->internallink(':plugin:'.$row['plugin'], ucfirst($row['plugin']). ' plugin');
			$R->doc .= '</div> '. hsc($row['description']);
            $R->doc .= '    </div></li>';
            $latest .= $row['plugin'].',';
		}
        $R->doc .= '</ul>';
    }

    /**
     * TODO
     */
    function _tagcloud(&$R, $data){
        $min  = 0;
        $max  = 0;
        $tags = array();
        $cloudmin = 0;
        if (is_numeric($data['cloudmin'])) {
            $cloudmin = (int)$data['cloudmin'];
        }

        $tagData =$this->hlp->getTags($cloudmin);
        // $tagData will be sorted by cnt (descending)
        foreach($tagData as $tag) {
            $tags[$tag['A.tag']] = $tag['cnt'];
            if(!$max) $max = $tag['cnt'];
            $min = $tag['cnt'];
        }

        $this->_cloud_weight($tags,$min,$max,5);

        ksort($tags);
        foreach($tags as $tag => $size){
            $R->doc .= '<a href="'.wl($this->getConf('main'),array('plugintag'=>$tag)).
                       '" class="wikilink1 cl'.$size.'" '.
                       'title="List all plugins with this tag">'.hsc($tag).'</a> ';
        }
    }

    /**
     * TODO
     */
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

    /**
     * TODO
     */
    function _showPluginTable($id, &$R, $data){
        $plugins = $this->hlp->getPlugins(array_merge($_REQUEST,$data));
        $popmax = $this->hlp->getMaxPopularity();
        if(!$allcnt) $allcnt = 1;

        $type = (int) $_REQUEST['plugintype'];
        $tag  = trim($_REQUEST['plugintag']);
        if($this->types[$type]){
            $header = 'Available '.$this->types[$type].' Plugins';
            $linkopt = "plugintype=$type,";
        }elseif($tag){
            $header = 'Available Plugins tagged with "'.hsc($tag).'"';
            $linkopt = "plugintag=".rawurlencode($tag).',';
        }else{
            $header = 'Available Plugins';
            $linkopt = '';
        }
        $header .= ' ('.count($plugins).')';

//TODO:        $R->header($header, 3, null);
        $R->section_open(3);
        $R->doc .= '<div id="pluginrepo__table">';

        if(!trim($_REQUEST['pluginsort'])) {
            $R->doc .= '<div class="repo_alphabet">Jump to plugins starting with: ';
            foreach (range('A', 'Z') as $char) {
                $R->doc .= '<a href="#'.strtolower($char).'">'.$char.'</a> ';
            }
            $R->doc .= '</div>';
        }

        if($type != 0 || $tag) {
            $R->doc .= '<div class="repo_resetfilter">';
            $R->doc .= $R->internallink($this->getConf('main'),'Show all plugins (remove filter)');
            $R->doc .= '</div>';
        }
        $R->doc .= '<div class="clearer"></div>';

        if ($this->getConf('new_table_layout')) {
            $this->_newTable($plugins,$popmax,$R);
        } else {
            $this->_classicTable($plugins,$popmax,$R);
        }

        $R->doc .= '</div>';
        $R->section_close();
        return true;
    }

    /**
     * TODO
     */
    function _newTable($plugins,$popmax,$R) {
// TODO: adv. sorting with arrows '<span>&darr;</span> ''<span>&uarr;</span> ' $ckey = '^'.$ckey;
        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=p').'" title="Sort by name">Plugin</a>
                            <div class="repo_authorsort"><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=a').'" title="Sort by author">Author</a></div></th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=d').'" title="Sort by date">Last Update</a></th>
                        <th>Compatible</th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=c').'" title="Sort by popularity">Popularity</a></th>
                    </tr>';

        foreach($plugins as $row) {
            if ($row['A.type'] == 32) {
                $link = $R->internallink(':template:'.$row['A.plugin'], ucfirst($row['A.plugin']).' template',null,true);
            } else {
                $link = $R->internallink(':plugin:'.$row['A.plugin'], ucfirst($row['A.plugin']).' plugin',null,true);
            }
            if(strpos($link,'class="wikilink2"')){
                $this->_delete($row['A.plugin']);
                continue;
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td>';
            $R->doc .= '<a name="'.substr($row['A.plugin'],0,1).'"></a>';

            $R->doc .= '<div class="repo_plugintitle">';
            $R->doc .= $link;
            $R->doc .= '</div>';
            if($row['A.downloadurl']){
                $R->doc .= '<div class="repo_download">';
                $R->doc .= $R->externallink($row['A.downloadurl'], 'Download', null, true);
                $R->doc .= '</div>';
            }
            $R->doc .= '<div class="clearer"></div>';
            $R->doc .= hsc($row['A.description']).'<br />';

            $R->doc .= '<div class="repo_provides">';
            $R->doc .= 'Provides: '.$this->hlp->listtype($row['A.type']) .' Tags:  ';
// TODO: add tags
            $R->doc .= '</div>';

            $R->doc .= '<div class="repo_mail">Author: ';
            $R->emaillink($row['A.email'],$row['A.author']);
            $R->doc .= '</div>';
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= hsc($row['A.lastupdate']);
            $R->doc .= '<br/>cnt = '.hsc($row['cnt']); // TODO: remove debug
            $R->doc .= '</td>';
// TODO: add template img

            // TODO: convert comp to something small
            $R->doc .= '<td>';
            $R->doc .= hsc($row['A.compatible']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            if(strpos($this->getConf('bundled'),$row['A.plugin']) === false){
                $R->doc .= '<div class="prog-border" title="'.$row['cnt'].'/'.$allcnt.'"><div class="prog-bar" style="width: '.sprintf(100*$row['cnt']/$popmax).'%;"></div></div>';
            }else{
                $R->doc .= '<i>bundled</i>';
            }
            $R->doc .= '</td>';

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }

    /**
     * TODO
     */
    function _classicTable($plugins,$popmax,$R) {
        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=p').'" title="Sort by name">Plugin</a></th>
                        <th>Description</th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=a').'" title="Sort by author">Author</a></th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=t').'" title="Sort by type">Type</a></th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=d').'" title="Sort by date">Last Update</a></th>
                        <th><a href="'.wl($this->getConf('main'),$linkopt.'pluginsort=c').'" title="Sort by popularity">Popularity</a></th>
                    </tr>';

        foreach($plugins as $row) {
            $link = $R->internallink(':plugin:'.$row['A.plugin'],null,null,true);
            if(strpos($link,'class="wikilink2"')){
                $this->_delete($row['A.plugin']);
                continue;
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td>';
            $R->doc .= $link;
            $R->doc .= '</td>';
            $R->doc .= '<td>';
            $R->doc .= '<strong>'.hsc($row['A.name']).'</strong><br />';
            $R->doc .= hsc($row['A.description']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->emaillink($row['A.email'],$row['A.author']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= $this->hlp->listtype($row['A.type']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= hsc($row['A.lastupdate']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            if(strpos($this->getConf('bundled'),$row['A.plugin']) === false){
                $R->doc .= '<div class="prog-border" title="'.$row['cnt'].'/'.$allcnt.'"><div class="prog-bar" style="width: '.sprintf(100*$row['cnt']/$popmax).'%;"></div></div>';
            }else{
                $R->doc .= '<i>bundled</i>';
            }
            $R->doc .= '</td>';

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }

    function _delete($plugin){
        $db = $this->hlp->_getPluginsDB();
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

}

