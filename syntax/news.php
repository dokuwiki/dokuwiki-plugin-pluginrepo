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

        $R->doc .= '<div class="repo__news">';
        $R->doc .= '<div class="repo__newsheader">'.hsc($data['headline']).'</div>';

        switch ($data['style']) {
            case 'sameauthor':
                $this->showSameAuthor($R,$data);
                break;
            default:
                $this->showDefault($R,$data);
        }

        if ($data['link']) {
            $R->doc .= '<div class="repo_newslink">';
            $R->internallink($data['link'],$data['linktext']);
            $R->doc .= '</div>';
        }
        $R->doc .= '</div>';
    }

    function showSameAuthor(&$R, $data) {
        global $ID;

        if (curNS($ID) == 'plugin') {
            $id = noNS($ID);
        } else {
            $id = curNS($ID).':'.noNS($ID);
        }
        $R->doc .= '<br />';

        $rel = $this->hlp->getPluginRelations($id);
        if (count($rel) == 0) {
            $R->doc .= "Can't find any other plugins";
            return;
        }

        $itr = 0;
        $R->doc .= '<ul>';
        while ($itr < count($rel['sameauthor']) && $itr < 10) {
            $R->doc .= '<li>'.$this->hlp->pluginlink($R,$rel['sameauthor'][$itr++]).'</li>';
        }
        $R->doc .= '</ul>';
    }

    function showDefault(&$R, $data) {
        $limit = (is_numeric($data['entries']) ? $data['entries']: 1);
        $plugins = $this->hlp->getPlugins($data);
        if ($data['random'] == 'no') {
            $start = 0;
        } else {
            $start = rand(0,count($plugins)-1-$limit);
        }
        for ($i = 0; $i < $limit; $i++) {
            $row = $plugins[$start+$i];
            $R->doc .= '<br />';
            $R->doc .= $this->hlp->pluginlink($R, $row['plugin'], ucfirst(noNS($row['plugin'])).($row['type']==32?' template':' plugin'));
            $R->doc .= '<p>'.$row['description'].'</p>';

            $val = $row['screenshot'];
            if ($val && $data['screenshot'] == 'yes') {
                $title = 'screenshot: '.basename(str_replace(':','/',$val));
                $R->doc .= '<a href="'.ml($val).'" class="media" rel="lightbox">';
                $R->doc .= '<img src="'.ml($val,"w=200").'" alt="'.hsc($title).'" width="200"/></a>';
            }

            $R->doc .= 'Author: ';
            $R->emaillink($row['email'],$row['author']);
            $R->doc .= '<br />';
        }
    }

}

