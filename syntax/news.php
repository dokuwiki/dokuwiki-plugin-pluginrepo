<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_pluginrepo_news extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $hlp = null;
    
    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_pluginrepo_news(){
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
        $this->Lexer->addSpecialPattern('----+ *pluginnews *-+\n.*?\n----+',$mode,'plugin_pluginrepo_news');
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
    function render($format, &$R, $data) {
        if($format != 'xhtml') return false;

        $db = $this->hlp->_getPluginsDB();
        if (!$db) return;

        $R->info['cache'] = false;

        return true;
    }

    function showNews() {
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
}

