<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
/**
 * Class syntax_plugin_pluginrepo_query
 */
class syntax_plugin_pluginrepo_query extends SyntaxPlugin
{
    /**
     * will hold the repository helper plugin
     * @var $hlp helper_plugin_pluginrepo_repository
     */
    public $hlp;
    public $allowedfields = ['plugin', 'name', 'description', 'author', 'email', 'compatible', 'lastupdate', 'type', 'securityissue', 'securitywarning', 'screenshot', 'downloadurl', 'bugtracker', 'sourcerepo', 'donationurl', 'tags', 'popularity'];

    /**
     * Constructor. Load helper plugin
     */
    public function __construct()
    {
        $this->hlp = plugin_load('helper', 'pluginrepo_repository');
        if (!$this->hlp) {
            msg('Loading the pluginrepo helper failed. Make sure the pluginrepo plugin is installed.', -1);
        }
    }

    /**
     * What kind of syntax are we?
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    public function getPType()
    {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    public function getSort()
    {
        return 155;
    }

    /**
     * Connect pattern to lexer
     *
     * @param string $mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *pluginquery *-+\n.*?\n----+', $mode, 'plugin_pluginrepo_query');
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return $this->hlp->parseData($match);
    }

    /**
     * Handles the actual output creation.
     *
     * @param string          $format   output format being rendered
     * @param Doku_Renderer   $R        the current renderer object
     * @param array           $data     data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($format, Doku_Renderer $R, $data)
    {
        if ($format != 'xhtml') {
            return false;
        }
        $db = $this->hlp->_getPluginsDB();
        if (!$db) {
            return false;
        }

        $R->info['cache'] = false;

        // sanitize SELECT input (data fields shown in separate columns)
        $fields = preg_split("/[;,\s]+/", $data['select']);
        $fields = array_filter($fields);
        $fields = array_unique($fields);
        $counter = count($fields);
        for ($fieldItr = 0; $fieldItr < $counter; $fieldItr++) {
            if (!in_array($fields[$fieldItr], $this->allowedfields)) {
                $R->doc .= '<div class="error repoquery"><strong>Repoquery error - Unknown field:</strong> ' . hsc($fields[$fieldItr]) . '</div>';
                return true;
            }
        }
        // create ORDER BY sql clause for shown fields, ensure 'plugin' field included
        $ordersql = 'plugin';
        if ($fields) {
            $ordersql = implode(',', $fields) . ',' . $ordersql;
        }

        // sanitize WHERE input
        if (!$data['where']) {
            $R->doc .= '<div class="error repoquery"><strong>Repoquery error - Missing WHERE clause</strong></div>';
            return true;
        }

        $error = $data['where'];
        foreach ($this->allowedfields as $field) {
            $error = str_replace($field, '', $error);
        }
        $error = preg_replace('/(LIKE|AND|OR|NOT|IS|NULL|[<>=\?\(\)])/i', '', $error);
        if (trim($error)) {
            $R->doc .= '<div class="error repoquery"><strong>Repoquery error - Unsupported chars in WHERE clause:</strong> ' . hsc($error) . '</div>';
            return true;
        }
        $wheresql = $data['where'];

        $stmt = $db->prepare("SELECT *
                                FROM plugins
                               WHERE $wheresql
                            ORDER BY $ordersql");

        // prepare VALUES input and execute query
        $values = preg_split("/,/", $data['values']);
        $values = array_map('trim', $values);
        $values = array_filter($values);
        if (!$values && array_key_exists('values', $data)) {
            $values = [''];
        }
        $stmt->execute($values);
        $datarows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$values) {
            $values = [''];
        }
        $headline = 'Plugins WHERE ' . vsprintf(str_replace('?', '%s', $wheresql), $values);

        $R->doc .= '<div class="pluginrepo_query">';
        if (count($fields) == 0) {
            // sort into alpha groups (A, B, C,...) if only displaying plugin links
            $plugingroups = [];
            foreach ($datarows as $row) {
                $firstchar = substr(noNS($row['plugin']), 0, 1);
                $plugingroups[$firstchar][] = $row['plugin'];
            }

            $R->doc .= '<div class="table">';
            $R->doc .= '<table class="inline">';
            $R->doc .= '<tr><th colspan="3">' . hsc($headline) . '</th></tr>';
            ksort($plugingroups);
            foreach ($plugingroups as $key => $plugins) {
                $R->doc .= '<tr>';

                $R->doc .= '<td>';
                $R->doc .= strtoupper($key);
                $R->doc .= '</td><td>';
                $R->doc .= count($plugins);
                $R->doc .= '</td><td>';
                $R->doc .= $this->hlp->listplugins($plugins, $R);
                $R->doc .= '</td>';

                $R->doc .= '</tr>' . DOKU_LF;
            }
            $R->doc .= '</table>';
            $R->doc .= '</div>';
        } else {
            // show values for all fields in separate columns
            $plugingroups = [];
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
                $R->doc .= '<th>' . ucfirst($field) . '</th>';
            }
            $R->doc .= '<th colspan="2">' . hsc($headline) . '</th></tr>';
            $prevkey = '';
            foreach ($datarows as $row) {
                $groupkey = '';
                foreach ($fields as $field) {
                    $groupkey .= $row[$field];
                }
                if ($groupkey === $prevkey) {
                    continue;
                }
                $prevkey = $groupkey;

                $R->doc .= '<tr>';
                foreach ($fields as $field) {
                    $R->doc .= '<td>';

                    if ($field == 'type') {
                        foreach ($this->hlp->types as $k => $v) {
                            if ($row['type'] & $k) {
                                $R->doc .= $v . ' ';
                            }
                        }
                    } elseif ($field == 'plugin') {
                        $R->doc .= $this->hlp->pluginlink($R, $row['plugin']);
                    } elseif ($field == 'email' || $field == 'author') {
                        $R->emaillink($row['email'], $row['author']);
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
                $R->doc .= $this->hlp->listplugins($plugins, $R);
                $R->doc .= '</td>';
                $R->doc .= '</tr>' . DOKU_LF;
            }
            $R->doc .= '</table>';
            $R->doc .= '</div>';
        }
        $R->doc .= '<p class="querytotal">∑ ' . count($datarows) . ' plugins matching query</p>';
        $R->doc .= '</div>';
        return true;
    }
}
