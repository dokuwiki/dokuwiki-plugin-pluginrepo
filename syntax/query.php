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
     * @var helper_plugin_pluginrepo_repository $hlp
     */
    public $hlp;
    public array $allowedfields = [
        'plugin', 'name', 'description', 'author', 'email', 'bestcompatible', 'compatible', 'lastupdate', 'type',
        'securityissue', 'securitywarning', 'updatemessage', 'screenshot', 'downloadurl', 'bugtracker', 'sourcerepo',
        'donationurl', 'tags', 'popularity'
    ];

    /**
     * Constructor. Load helper plugin
     */
    public function __construct()
    {
        $this->hlp = plugin_load('helper', 'pluginrepo_repository');
        if (!$this->hlp instanceof helper_plugin_pluginrepo_repository) {
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
        $initialData = [
            //query
            'select' => '',
            'where' => '',
            'values' => '',
            'headline' => ''
        ];
        return $this->hlp->parseData($match, $initialData);
    }

    /**
     * Handles the actual output creation.
     *
     * @param string          $format   output format being rendered
     * @param Doku_Renderer   $renderer the current renderer object
     * @param array           $data     data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format != 'xhtml') {
            return false;
        }

        $db = $this->hlp->getPluginsDB();
        if (!$db) {
            return false;
        }

        /** @var Doku_Renderer_xhtml $renderer */
        $renderer->info['cache'] = false;

        // sanitize SELECT input (data fields shown in separate columns)
        $fields = preg_split("/[;,\s]+/", $data['select']);
        $fields = array_filter($fields);
        $fields = array_unique($fields);
        $fields = array_values($fields); //reindex

        $counter = count($fields);
        for ($fieldItr = 0; $fieldItr < $counter; $fieldItr++) {
            if (!in_array($fields[$fieldItr], $this->allowedfields)) {
                $renderer->doc .= '<div class="error repoquery">'
                    . '<strong>Repoquery error - Unknown field:</strong> ' . hsc($fields[$fieldItr])
                    . '</div>';
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
            $renderer->doc .= '<div class="error repoquery">'
                . '<strong>Repoquery error - Missing WHERE clause</strong>'
                . '</div>';
            return true;
        }

        $error = $data['where'];
        foreach ($this->allowedfields as $field) {
            $error = str_replace($field, '', $error);
        }
        $error = preg_replace('/(LIKE|AND|OR|NOT|IS|NULL|[<>=?()&])/i', '', $error);
        if (trim($error)) {
            $renderer->doc .= '<div class="error repoquery">'
                . '<strong>Repoquery error - Unsupported chars in WHERE clause:</strong> ' . hsc($error)
                . '</div>';
            return true;
        }
        $wheresql = $data['where'];

        $stmt = $db->prepare("SELECT *
                                FROM plugins
                               WHERE $wheresql
                            ORDER BY $ordersql");

        // prepare VALUES input and execute query
        $datePlaceholders = [
            '@DATEMOSTRECENT@', '@DATESECONDMOSTRECENT@', '@DATETHIRDMOSTRECENT@',
            '@DATEFOURTHMOSTRECENT@'
        ];
        $rows = 0;
        $recentDates = [];
        foreach ($this->hlp->getDokuReleases() as $release) {
            if (++$rows > 4) {
                break;
            }
            $recentDates[] = $release['date'];
        }

        $values = explode(",", $data['values']);
        $values = array_map('trim', $values);
        $values = array_filter($values);
        $values = array_map(static fn($value) => str_replace($datePlaceholders, $recentDates, $value), $values);
        if (!$values && array_key_exists('values', $data)) {
            $values = [''];
        }
        $stmt->execute($values);
        $datarows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$values) {
            $values = [''];
        }
        if ($data['headline']) {
            $headline = $data['headline'];
        } else {
            $headline = 'Plugins WHERE ' . vsprintf(str_replace('?', '%s', $wheresql), $values);
        }

        $renderer->doc .= '<div class="pluginrepo_query">';
        $plugingroups = [];
        if (count($fields) == 0) {
            // sort into alpha groups (A, B, C,...) if only displaying plugin links
            foreach ($datarows as $row) {
                $firstchar = substr(noNS($row['plugin']), 0, 1);
                $plugingroups[$firstchar][] = $row['plugin'];
            }

            $renderer->doc .= '<div class="table">';
            $renderer->doc .= '<table class="inline">';
            $renderer->doc .= '<tr><th colspan="3">' . hsc($headline) . '</th></tr>';
            ksort($plugingroups);
            foreach ($plugingroups as $key => $plugins) {
                $renderer->doc .= '<tr>';

                $renderer->doc .= '<td>';
                $renderer->doc .= strtoupper($key);
                $renderer->doc .= '</td><td>';
                $renderer->doc .= count($plugins);
                $renderer->doc .= '</td><td>';
                $renderer->doc .= $this->hlp->listplugins($plugins, $renderer);
                $renderer->doc .= '</td>';

                $renderer->doc .= '</tr>';
            }
        } else {
            // show values for all fields in separate columns
            foreach ($datarows as $row) {
                $groupkey = '';
                foreach ($fields as $field) {
                    $groupkey .= $row[$field];
                }
                $plugingroups[$groupkey][] = $row['plugin'];
            }

            $renderer->doc .= '<div class="table">';
            $renderer->doc .= '<table class="inline">';
            $renderer->doc .= '<tr>';
            foreach ($fields as $field) {
                $renderer->doc .= '<th>' . ucfirst($field) . '</th>';
            }
            $renderer->doc .= '<th colspan="2">' . hsc($headline) . '</th></tr>';
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

                $renderer->doc .= '<tr>';
                foreach ($fields as $field) {
                    $renderer->doc .= '<td>';

                    if ($field == 'type') {
                        foreach ($this->hlp->types as $k => $v) {
                            if ($row['type'] & $k) {
                                $renderer->doc .= $v . ' ';
                            }
                        }
                    } elseif ($field == 'plugin') {
                        $renderer->doc .= $this->hlp->pluginlink($renderer, $row['plugin']);
                    } elseif ($field == 'email' || $field == 'author') {
                        $renderer->emaillink($row['email'], $row['author']);
                    } else {
                        $renderer->doc .= hsc($row[$field]);
                    }
                    $renderer->doc .= '</td>';
                }
                $plugins = $plugingroups[$groupkey];
                $renderer->doc .= '<td>';
                $renderer->doc .= count($plugins);
                $renderer->doc .= '</td>';
                $renderer->doc .= '<td>';
                $renderer->doc .= $this->hlp->listplugins($plugins, $renderer);
                $renderer->doc .= '</td>';
                $renderer->doc .= '</tr>';
            }
        }
        $renderer->doc .= '</table>';
        $renderer->doc .= '</div>';
        $renderer->doc .= '<p class="querytotal">∑ ' . count($datarows) . ' plugins matching query</p>';
        $renderer->doc .= '</div>';
        return true;
    }
}
