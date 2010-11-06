<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if (!defined('DOKU_LF')) define('DOKU_LF', "\n");

class syntax_plugin_pluginrepo_query extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $hlp = null;
    var $allowedfields = array('plugin','name','description','author','email','compatible',
                               'lastupdate','type','securityissue','securitywarning','screenshot',
                               'downloadurl','bugtracker','sourcerepo','donationurl','tags','cnt');
    
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

        // sanitize SELECT input (data fields shown in separate columns)
        $fields = preg_split("/[;,\s]+/",$data['select']);
        $fields = array_filter($fields);
        $fields = array_unique($fields);
        for ($fieldItr = 0; $fieldItr < count($fields); $fieldItr++) {
            if (!in_array($fields[$fieldItr], $this->allowedfields)) {
                $R->doc .= '<b>Repoquery error - Unknown field:</b> '.hsc($fields[$fieldItr]).'<br/>';
                return;
            }
            if ($fields[$fieldItr] != 'cnt') {
                $fields[$fieldItr] = 'A.'.$fields[$fieldItr];
            }
        }
        // create ORDER BY sql clause for shown fields, ensure 'plugin' field included 
        $ordersql = join(',', array_merge($fields,array('A.plugin')));

        // sanitize WHERE input
        if (!$data['where']) {
            $R->doc .= '<b>Repoquery error - Missing WHERE clause</b><br/>';
            return;
        } elseif (strpos($data['where'],'cnt')) {
            $R->doc .= '<b>Repoquery error - "cnt" could not be used with WHERE, use HAVING instead.</b><br/>';
            return;
        }

        $error = $data['where'];
        foreach ($this->allowedfields as $field) {
            $error = str_replace($field,'',$error);
        }
        $error = preg_replace('/(LIKE|AND|OR|NOT|IS|NULL|[<>=\?\(\)])/i','',$error);
        if (trim($error)) {
            $R->doc .= '<b>Repoquery error - Unsupported chars in WHERE clause:</b> '.hsc($error).'<br/>';
            return;
        }
        $wheresql = $data['where'];

        // sanitize HAVING input
        if (preg_match('/^cnt\s*[=><]+\s*\d+$/i',$data['having'])) {
            $havingsql = 'HAVING '.$data['having'];
        } elseif ($data['having']) {
            $R->doc .= '<b>Repoquery error - Unsupported chars in HAVING clause:</b> '.hsc($data['having']).'<br/>';
            return;
        }
        
        $stmt = $db->prepare("SELECT A.*, COUNT(C.value) as cnt
                                FROM plugins A LEFT JOIN popularity C ON A.plugin = C.value AND C.key = 'plugin'
                               WHERE $wheresql 
                            GROUP BY A.plugin
                             $havingsql
                            ORDER BY $ordersql");

        // prepare VALUES input and execute query
        $values = preg_split("/,/",$data['values']);
        $values = array_map('trim',$values);
        $values = array_filter($values);
        $stmt->execute($values);
        $datarows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headline = 'Plugins WHERE '.vsprintf(str_replace('?','%s',$wheresql),$values);
        $headline .= ($havingsql?' '.$havingsql:'');

        $R->doc .= '<div class="pluginrepo__query">';
        if (count($fields) == 0) {
            // sort into alpha groups if only displaying plugin links
            $plugingroups = array();
            foreach ($datarows as $row) {
                $firstchar = substr(noNS($row['A.plugin']),0,1);
                $plugingroups[$firstchar][] = $row['A.plugin'];
            }

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

        } else {
            // show values for all fields in separate columns
            $plugingroups = array();
            foreach ($datarows as $row) {
                $groupkey = '';
                foreach ($fields as $field) {
                    $groupkey .= $row[$field];
                }
                $plugingroups[$groupkey][] = $row['A.plugin'];
            }

            $R->doc .= '<table class="inline">';
            $R->doc .= '<tr>';
            foreach ($fields as $field) {
                $R->doc .= '<th>'.ucfirst(str_replace('A.','',$field)).'</th>';
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

                    if ($field == 'A.type') {
                        $R->doc .= $this->hlp->listtype($row['A.type']);

                    } elseif ($field == 'A.plugin') {
                        $R->doc .= $this->hlp->internallink($R,$row['A.plugin']);

                    } elseif ($field == 'A.email' || $field == 'A.author') {
                        $R->doc .= $R->emaillink($row['A.email'],$row['A.author']);

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
        }
        $R->doc .= '<div class="pluginrepo__querytotal">'.count($datarows).' plugins matching query</div>';
        $R->doc .= '</div>';
        return true;
    }

}

