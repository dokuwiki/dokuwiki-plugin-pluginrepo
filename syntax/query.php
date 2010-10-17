<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_pluginrepo_query extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $hlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_pluginrepo_query(){
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
        $this->Lexer->addSpecialPattern('----+ *pluginquery *-+\n.*?\n----+',$mode,'plugin_pluginrepo_query');
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

        if ($data['show'] = 'value') {
        }
        // TODO: show value of field then no grouping, maybe group by value instead
        // TODO: advanced query
        $stmt = $db->prepare('SELECT plugin FROM plugins WHERE downloadurl <> ?');
//        $stmt->execute(array($data['field'],$data['value']));
        $stmt->execute(array(''));
        $plugingroups = array();
        foreach ($stmt as $row) {
            $plugingroups[substr($row['plugin'],0,1)][] = $R->internallink(':plugin:'.$row['plugin'],null,null,true);
        }

        $R->header('Plugins matching '.$data['field'].' '.$data['query'].' '.$data['value'] , 2, null);
        $R->section_open(2);

        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th colspan="3">Plugins</th>
                    </tr>';

        foreach($plugingroups as $key => $plugins) {
            $R->doc .= '<tr>';

            $R->doc .= '<td>';
            $R->doc .= $key;
            $R->doc .= '</td><td>';
            $R->doc .= count($plugins);
            $R->doc .= '</td><td>';
            $R->doc .= join(', ', $plugins);
            $R->doc .= '</td>';

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';

        $R->section_close();
        return true;
    }

}

