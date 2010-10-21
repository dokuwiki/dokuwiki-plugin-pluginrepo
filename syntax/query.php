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
    var $allowedfields = array('plugin','name','description','author','email',
                               'compatible','lastupdate','type','securityissue',
                               'downloadurl','bugtracker','sourcerepo','donationurl');
    
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

        // sanitize input
        $data['select'] = 'plugin '.$data['select'];
        $fields = preg_split("/[[;,\s]]+/",$data['select']);
        $fields = array_filter($fields);
        $fields = array_unique($fields);
        foreach ($fields as $field) {
            if (!in_array($field, $this->allowedfields)) {
                $R->doc .= "<b>Repoquery error - Unknown field:</b> $field<br/>";
                return;
            }
        }
        $selectsql = 'A.'.join(',A.', $fields);
        $ordersql = str_replace('A.plugin,', '', $selectsql);

        if (!$data['where']) {
            $R->doc .= "<b>Repoquery error - Missing WHERE clause</b><br/>";
            return;
        }
        $wheresql = $data['where'];
        // TODO: protect advanced where query

        $stmt = $db->prepare("SELECT $selectsql 
                                FROM plugins A
                               WHERE $wheresql 
                            GROUP BY plugin
                            ORDER BY $ordersql");
        $stmt->execute(array(''));


        if (count($fields) == 1) {
            // sort into alpha groups if only displaying plugin links
            $plugingroups = array();
            foreach ($stmt as $row) {
                $plugingroups[substr($row['A.plugin'],0,1)][] = $R->internallink(':plugin:'.$row['A.plugin'],null,null,true);
            }

            $R->doc .= '<table class="inline">';
            $R->doc .= '<tr><th colspan="3">Plugins WHERE '.$wheresql.'</th></tr>';
            foreach($plugingroups as $key => $plugins) {
                $R->doc .= '<tr>';

                $R->doc .= '<td>';
                $R->doc .= strtoupper($key);
                $R->doc .= '</td><td>';
                $R->doc .= count($plugins);
                $R->doc .= '</td><td>';
                $R->doc .= join(', ', $plugins);
                $R->doc .= '</td>';

                $R->doc .= '</tr>';
            }
            $R->doc .= '</table>';

        } else {
            $plugingroups = array();
            foreach ($stmt as $row) {
                $plugingroups[$row['A.'.$fields[count($fields)-1]]][] = $R->internallink(':plugin:'.$row['A.plugin'],null,null,true);
            }
 
            $R->doc .= '<table class="inline">';
            $R->doc .= '<tr>';
            for($fieldItr = 1; $fieldItr < count($fields); $fieldItr++) {
                $R->doc .= '<th>'.$fields[$fieldItr].'</th>';
            }
            $R->doc .= '<th colspan="2">Plugins WHERE '.$wheresql.'</th></tr>';
            $prevrow = '';
            foreach ($stmt as $row) {
                $thisrow = '';
                for($fieldItr = 1; $fieldItr < count($fields); $fieldItr++) {
                    $thisrow .= '<td>';
                    $thisrow .= $row['A.'.$fields[$fieldItr]];
                    $thisrow .= '</td>';
                }
         //       if ($thisrow == $prevrow) continue;

                $R->doc .= '<tr>';
                $R->doc .= $thisrow;
                $prevrow == $thisrow;
                $plugins = $plugingroups[$row['A.'.$fields[count($fields)-1]]];
                $R->doc .= '<td>';
                $R->doc .= count($plugins);
                $R->doc .= '</td>';
                $R->doc .= '<td>';
                $R->doc .= join(', ', $plugins);
                $R->doc .= '</td>';
                $R->doc .= '</tr>';
            }
            $R->doc .= '</table>';
        }


        return true;
    }

}

