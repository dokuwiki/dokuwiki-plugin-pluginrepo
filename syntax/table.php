<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

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
// TODO: use lang depening on namespace


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
        $R->doc .= '    </div>';

        $R->doc .= '    <div class="repo_info2">';
        $this->_showPluginTypeFilter($id, &$R, $data);
        $R->doc .= '    </div>';

        $R->doc .= '    <div class="repo_info3">';
        $this->_showPluginNews($id, &$R, $data);
        $R->doc .= '    </div>';
        $R->doc .= '  </div>';

        $R->doc .= '  <div class="repo_cloud">';
        $R->doc .= '<h3>Filter plugins by tag</h3>';
 //TODO:       $this->_tagcloud($R);
        $R->doc .= '  </div>';

        $R->doc .= '</div>';
        $R->section_close();

 //       $this->_showPluginTable($id, &$R, $data);
    }


    function _showMainSearch($id, &$R, $data){
        $R->doc .= '<p>There are many ways to search among available DokuWiki plugins.
  		You may filter the list by tags from the cloud to the left or
                    by type. Of cause you can also use the search box.</p>';

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


    function _showPluginTypeFilter($id, &$R, $data){
        $R->doc .= '<h3>Filter plugins by type</h3>';
        $R->doc .= 'DokuWiki features different plugin types.';
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

    function _showPluginTable($id, &$R, $data){
    // TODO: use preList/postList as DATA plugin

    // TODO: get maximum pop
        // $sql = "SELECT COUNT(uid) as cnt
                  // FROM popularity
                 // WHERE `key` = 'plugin'";
        // foreach($this->bundled as $bnd){
            // $sql .= " AND `value` != '$bnd'";
        // }
        // $sql .= "GROUP BY `value`
                 // ORDER BY cnt DESC
                 // LIMIT 1";
        // $res = mysql_query($sql,$this->db);
        // $row = mysql_fetch_assoc($res);
        // $popmax = $row['cnt'];
        if(!$popmax) $popmax = 1;
        // mysql_free_result($res);

        // get maximum pop
        // $sql = "SELECT COUNT(DISTINCT uid) as cnt
                  // FROM popularity
                 // WHERE `key` = 'plugin'
                   // AND `value` = 'popularity'";
        // $res = mysql_query($sql,$this->db);
        // $row = mysql_fetch_assoc($res);
        // $allcnt = $row['cnt'];
        // if(!$allcnt) $allcnt = 1;
        // mysql_free_result($res);

        $type = (int) $_REQUEST['plugintype'];
        $tag  = trim($_REQUEST['plugintag']);
//        $sort = trim($_REQUEST['pluginsort']);

        $plugins = $this->hlp->getPlugins($_REQUEST);

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
        $R->doc .=          $this->hlp->listtype(1+2+4+8+16,'</li><li>');
        $R->doc .= '    </li>';
        $R->doc .= '</ul>';
        $R->doc .= '</div>';

        $R->doc .= '<div class="repo_cloud">';
// TODO:       $this->_tagcloud($R);
        $R->doc .= '</div>';

        $R->doc .= '<div class="clearer"></div>';

        $this->_newTable($plugins,$popmax,$R);
//        $this->_classicTable($plugins,$popmax,$R);

        $R->doc .= '</div>';
        $R->section_close();
        return true;
    }

    function _newTable($plugins,$popmax,$R) {
        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th><a href="'.wl($this->conf['main'],$linkopt.'pluginsort=p').'" title="Sort by name">Plugin</a></th>
                        <th>Description</th>
                        <th><a href="'.wl($this->conf['main'],$linkopt.'pluginsort=a').'" title="Sort by author">Author</a></th>
                        <th><a href="'.wl($this->conf['main'],$linkopt.'pluginsort=t').'" title="Sort by type">Type</a></th>
                        <th><a href="'.wl($this->conf['main'],$linkopt.'pluginsort=d').'" title="Sort by date">Last Update</a></th>
                        <th><a href="'.wl($this->conf['main'],$linkopt.'pluginsort=c').'" title="Sort by popularity">Popularity</a></th>
                    </tr>';

        foreach($plugins as $row) {
            $link = $R->internallink(':plugin:'.$row['A.plugin'],null,null,true);
            if(strpos($link,'class="wikilink2"')){
// TODO:                $this->_delete($row['plugin']);
                continue;
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td>';
            $R->doc .= $link.' Plugin<br />';
//            $R->doc .= '<strong>'.hsc($row['A.name']).'</strong><br />';
            $R->doc .= hsc($row['A.description']).'<br />';
            $R->doc .= 'Provides:'.$this->hlp->listtype($row['A.type']) .'  Similar to:  ';

            $R->emaillink($row['A.email'],$row['A.author']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= hsc($row['A.lastupdate']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            if(strpos($this->getConf('bundled'),$row['plugin']) === false){
                $R->doc .= '<div class="prog-border" title="'.$row['A.cnt'].'/'.$allcnt.'"><div class="prog-bar" style="width: '.sprintf(100*$row['A.cnt']/$popmax).'%;"></div></div>';
            }else{
                $R->doc .= '<i>bundled</i>';
            }
            $R->doc .= '</td>';

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }

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
// TODO:                $this->_delete($row['plugin']);
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
            if(strpos($this->getConf('bundled'),$row['plugin']) === false){
                $R->doc .= '<div class="prog-border" title="'.$row['A.cnt'].'/'.$allcnt.'"><div class="prog-bar" style="width: '.sprintf(100*$row['A.cnt']/$popmax).'%;"></div></div>';
            }else{
                $R->doc .= '<i>bundled</i>';
            }
            $R->doc .= '</td>';

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }
}

