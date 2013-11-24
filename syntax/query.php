<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_pluginrepo_query extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the repository helper plugin
     */
    var $hlp = null;
    var $allowedfields = array('plugin','name','description','author','email','compatible',
                               'lastupdate','type','securityissue','securitywarning','screenshot',
                               'downloadurl','bugtracker','sourcerepo','donationurl','tags','popularity');

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_pluginrepo_query(){
        $this->hlp = plugin_load('helper', 'pluginrepo');
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
    function handle($match, $state, $pos, Doku_Handler &$handler){
        return $this->hlp->parseData($match);
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer &$R, $data) {
        if($format != 'xhtml') return false;

        $db = $this->hlp->_getPluginsDB();
        if (!$db) return;

        $R->info['cache'] = false;

        // sanitize SELECT input (data fields shown in separate columns)
        $fields = preg_split("/[;,\s]+/",$data['select']);
        $fields = array_filter($fields);
        $fields = array_unique($fields);
        for ($fieldItr = 0; $fieldItr < count($fields); $fieldItr++) {
            if (!in_array($fields[$fieldItr], $this->allowedfields)) {
                $R->doc .= '<div class="error repoquery"><strong>Repoquery error - Unknown field:</strong> '.hsc($fields[$fieldItr]).'</div>';
                return;
            }
        }
        // create ORDER BY sql clause for shown fields, ensure 'plugin' field included
        $ordersql = 'plugin';
        if ($fields) {
            $ordersql = join(',',$fields).','.$ordersql;
        }

        // sanitize WHERE input
        if (!$data['where']) {
            $R->doc .= '<div class="error repoquery"><strong>Repoquery error - Missing WHERE clause</strong></div>';
            return;
        }

        $error = $data['where'];
        foreach ($this->allowedfields as $field) {
            $error = str_replace($field,'',$error);
        }
        $error = preg_replace('/(LIKE|AND|OR|NOT|IS|NULL|[<>=\?\(\)])/i','',$error);
        if (trim($error)) {
            $R->doc .= '<div class="error repoquery"><strong>Repoquery error - Unsupported chars in WHERE clause:</strong> '.hsc($error).'</div>';
            return;
        }
        $wheresql = $data['where'];

        $stmt = $db->prepare("SELECT *
                                FROM plugins
                               WHERE $wheresql
                            ORDER BY $ordersql");

        // prepare VALUES input and execute query
        $values = preg_split("/,/",$data['values']);
        $values = array_map('trim',$values);
        $values = array_filter($values);
        if (!$values && array_key_exists('values',$data)) $values = array('');
        $stmt->execute($values);
        $datarows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$values) $values = array('');
        $headline = 'Plugins WHERE '.vsprintf(str_replace('?','%s',$wheresql),$values);

        $R->doc .= '<div class="pluginrepo_query">';
        if (count($fields) == 0) {
            // sort into alpha groups if only displaying plugin links
            $plugingroups = array();
            foreach ($datarows as $row) {
                $firstchar = substr(noNS($row['plugin']),0,1);
                $plugingroups[$firstchar][] = $row['plugin'];
            }

            $R->doc .= '<div class="table">';
            $R->doc .= '<table class="inline">';
            $R->doc .= '<tr><th colspan="3">'.hsc($headline).'</th></tr>';
            ksort($plugingroups);
            foreach($plugingroups as $key => $plugins) {
                $R->doc .= '<tr>';

                $R->doc .= '<td>';
                $R->doc .= strtoupper($key);
                $R->doc .= '</td><td>';
                $R->doc .= count($plugins);
                $R->doc .= '</td><td>';
                $R->doc .= $this->hlp->listplugins($plugins,$R);
                $R->doc .= '</td>';

                $R->doc .= '</tr>'.DOKU_LF;
            }
            $R->doc .= '</table>';
            $R->doc .= '</div>';

        } else {
            // show values for all fields in separate columns
            $plugingroups = array();
            foreach ($datarows as $row) {
                $groupkey = '';
                foreach ($fields as $field) {
                    $groupkey .= $row[$field];
                }
                $plugingroups[$groupkey][] = $row['plugin'];
            }

            $R->doc .= '<div class="table">';
            $R->doc .= '<table class="inline">';
            $R->doc .= '<tr>';
            foreach ($fields as $field) {
                $R->doc .= '<th>'.ucfirst($field).'</th>';
            }
            $R->doc .= '<th colspan="2">'.hsc($headline).'</th></tr>';
            $prevkey = '';
            foreach ($datarows as $row) {
                $groupkey = '';
                foreach ($fields as $field) {
                    $groupkey .= $row[$field];
                }
                if ($groupkey == $prevkey) continue;
                $prevkey = $groupkey;

                $R->doc .= '<tr>';
                foreach ($fields as $field) {
                    $R->doc .= '<td>';

                    if ($field == 'type') {
                        foreach($this->hlp->types as $k => $v){
                            if($row['type'] & $k){
                                $R->doc .= $v.' ';
                            }
                        }

                    } elseif ($field == 'plugin') {
                        $R->doc .= $this->hlp->pluginlink($R,$row['plugin']);

                    } elseif ($field == 'email' || $field == 'author') {
                        $R->doc .= $R->emaillink($row['email'],$row['author']);

                    } else {
                        $R->doc .= hsc($row[$field]);
                    }
                    $R->doc .= '</td>';
                }
                $plugins = $plugingroups[$groupkey];
                $R->doc .= '<td>';
                $R->doc .= count($plugins);
                $R->doc .= '</td>';
                $R->doc .= '<td>';
                $R->doc .= $this->hlp->listplugins($plugins,$R);
                $R->doc .= '</td>';
                $R->doc .= '</tr>'.DOKU_LF;
            }
            $R->doc .= '</table>';
            $R->doc .= '</div>';
        }
        $R->doc .= '<p class="querytotal">∑ '.count($datarows).' plugins matching query</p>';
        $R->doc .= '</div>';
        return true;
    }

}

