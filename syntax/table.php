<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
/**
 * Class syntax_plugin_pluginrepo_table
 */
class syntax_plugin_pluginrepo_table extends SyntaxPlugin
{
    /**
     * will hold the repository helper plugin
     * @var helper_plugin_pluginrepo_repository $hlp
     */
    public $hlp;

    /**
     * Constructor. Load helper plugin
     */
    public function __construct()
    {
        $this->hlp = plugin_load('helper', 'pluginrepo_repository');
        if (!$this->hlp instanceof helper_plugin_pluginrepo_repository) {
            msg('Loading the pluginrepo repository helper failed. Make sure the pluginrepo plugin is installed.', -1);
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
        $this->Lexer->addSpecialPattern('~~pluginrepo~~', $mode, 'plugin_pluginrepo_table');
        $this->Lexer->addSpecialPattern('----+ *pluginrepo *-+\n.*?\n----+', $mode, 'plugin_pluginrepo_table');
    }

    /**
     * Handle the match - parse the data
     *
     * This parsing is shared between the multiple different output/control
     * syntaxes
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
            'cloudmin' => 0,
            'showscreenshot' => false,
            'showcompatible' => false,
            //also used via filter, if default value are modified check also showPluginTable()
            'showall' => false,
            'includetemplates' => false,
            'pluginsort' => '', //str, (short or long names)
            'plugintag' => '', //str
            'plugintype' => 0, //int
            'plugins' => '', //csv (accepted as request var, but not used?)
        ];
        return $this->hlp->parseData($match, $initialData);
    }

    /**
     * Create output
     *
     * @param string          $format   output format being rendered
     * @param Doku_Renderer   $renderer the current renderer object
     * @param array           $data     data created by handle()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format == 'xhtml') {
            /** @var Doku_Renderer_xhtml $renderer */
            return $this->showData($renderer, $data);
        }
        return false;
    }

    /**
     * Output table of plugins with filter and navigation
     *
     * @param Doku_Renderer_xhtml $R
     * @param array               $data
     * @return bool rendered correctly?
     */
    public function showData($R, $data)
    {
        global $ID;

        $R->info['cache'] = false;
        $R->header($this->getLang('t_search_' . noNS($ID)), 2, 0);
        $R->section_open(2);

        $R->doc .= '<div class="pluginrepo_table">';

        // filter and search
        $R->doc .= '<div class="repoFilter">';
        $this->showMainSearch($R);
        if ($data['plugintype'] === 0) {
            $this->showPluginTypeFilter($R, $data);
        }
        $R->doc .= '</div>';

        // tag cloud
        $R->doc .= '<div class="repoCloud">';
        $this->tagcloud($R, $data);
        $R->doc .= '</div>';

        $R->doc .= '<div class="clearer"></div>';
        $R->doc .= '</div>';// pluginrepo_table
        $R->section_close();

        // main table
        $this->showPluginTable($R, $data);
        return true;
    }

    /**
     * Output repo table overview/intro and search form
     *
     * @param Doku_Renderer_xhtml $R
     */
    public function showMainSearch($R)
    {
        global $ID;
        if (substr($ID, -1, 1) == 's') {
            $searchNS = substr($ID, 0, -1);
        } else {
            $searchNS = $ID;
        }

        $intro = $this->getLang('t_searchintro_' . noNS($ID));
        $url = wl();
        $R->doc .= <<<HTML
        <p>
            $intro
        </p>
        <form action="$url" accept-charset="utf-8" class="plugin-search" id="dw__search2" method="get">
            <div class="no">
                <input type="hidden" name="do" value="search" />
                <input type="hidden" id="dw__ns" name="ns" value="$searchNS" />
                <input type="text" id="qsearch2__in" accesskey="f" name="id" class="edit" />
                <input type="submit" value="{$this->getLang('t_btn_search')}"
                       class="button" title="{$this->getLang('t_btn_searchtip')}" />
                <div id="qsearch2__out" class="ajax_qsearch JSpopup"></div>
            </div>
        </form>
        HTML;
    }

    /**
     * Output plugin TYPE filter selection
     *
     * @param Doku_Renderer_xhtml $R
     * @param array $data
     */
    public function showPluginTypeFilter($R, $data)
    {
        global $ID;

        $R->doc .= '<h3>';
        $R->doc .= $this->getLang('t_filterbytype');
        $R->doc .= '</h3>';

        $R->doc .= '<ul class="types">';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typesyntax'), $this->hlp->listtype(1, $ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typeaction'), $this->hlp->listtype(4, $ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typeadmin'), $this->hlp->listtype(2, $ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typehelper'), $this->hlp->listtype(16, $ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typerender'), $this->hlp->listtype(8, $ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typeremote'), $this->hlp->listtype(64, $ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typeauth'), $this->hlp->listtype(128, $ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typecli'), $this->hlp->listtype(256, $ID));
        $R->doc .= '</div></li>';

        if ($data['includetemplates']) {
            $R->doc .= '<li><div class="li">';
            $R->doc .= sprintf($this->getLang('t_typetemplate'), $this->hlp->listtype(32, $ID));
            $R->doc .= '</div></li>';
        }
        $R->doc .= '</ul>';
    }

    /**
     * Output plugin tag filter selection (cloud)
     *
     * @param Doku_Renderer_xhtml $R
     * @param array $data with entries used:
     *  <ul>
     *      <li>'cloudmin' int,</li>
     *      <li>'showall' => bool,</li>
     *      <li>'plugintype' => 32 or different type,</li>
     *      <li>'includetemplates' => bool</li>
     *  </ul>
     */
    public function tagcloud($R, $data)
    {
        global $ID;

        $R->doc .= '<h3>';
        $R->doc .= $this->getLang('t_filterbytag');
        $R->doc .= '</h3>';

        $min  = 0;
        $max  = 0;
        $tags = [];

        $tagData = $this->hlp->getTags($data['cloudmin'], $data);
        // $tagData will be sorted by cnt (descending)
        foreach ($tagData as $tag) {
            // obsolete plugins are not included in the table
            if ($tag['tag'] == $this->hlp->obsoleteTag) {
                continue;
            }
            $tags[$tag['tag']] = $tag['cnt'];
            if (!$max) {
                $max = $tag['cnt'];
            }
            $min = $tag['cnt'];
        }
        $this->cloudWeight($tags, $min, $max, 5);

        ksort($tags);
        if (count($tags) > 0) {
            $R->doc .= '<div class="cloud">';
            foreach ($tags as $tag => $size) {
                $R->doc .= '<a href="' . wl($ID, ['plugintag' => $tag]) . '#extension__table" ' .
                           'class="wikilink1 cl' . $size . '" ' .
                           'title="List all plugins with this tag">' . hsc($tag) . '</a> ';
            }
            $R->doc .= '</div>';
        }
    }

    /**
     * Assign weight group to each tag in supplied array, use $levels groups
     *
     * @param array $tags
     * @param int   $min
     * @param int   $max
     * @param int   $levels
     */
    public function cloudWeight(&$tags, $min, $max, $levels)
    {
        // calculate tresholds
        $tresholds = [];
        for ($i = 0; $i <= $levels; $i++) {
            $tresholds[$i] = ($max - $min + 1) ** ($i / $levels) + $min - 1;
        }

        // assign weights
        foreach ($tags as $tag => $cnt) {
            foreach ($tresholds as $tresh => $val) {
                if ($cnt <= $val) {
                    $tags[$tag] = $tresh;
                    break;
                }
                $tags[$tag] = $levels;
            }
        }
    }

    /**
     * Output plugin table and "jump to A B C.." navigation
     *
     * @param Doku_Renderer_xhtml $R
     * @param array $data with entries used:
     *  <ul>
     *      <li>via getPlugins():
     *          <ul>
     *              <li>'plugins' array or str, if used plugintype and plugintag are skipped</li>
     *              <li>'plugintype' int,</li>
     *              <li>'plugintag' str</li>
     *              <li>'pluginsort' str shortcuts assumed</li>
     *              <li>'showall' bool</li>
     *              <li>'includetemplates' bool</li>
     *          </ul>
     *      </li>
     *      <li>via showTable():
     *          <ul>
     *              <li>'showcompatible' bool</li>
     *              <li>'showscreenshot' bool</li>
     *          </ul>
     *      </li>
     *  </ul>
     * @return bool
     * @see helper_plugin_pluginrepo_repository::getPlugins()
     */
    public function showPluginTable($R, $data)
    {
        global $ID, $INPUT;

        //if set in syntax it overrides the url parameters
        $request = [
            'plugins' => $data['plugins'] ?: $INPUT->arr('plugins'), //only array format as url parameter
            'plugintype' => $data['plugintype'] ?: $INPUT->int('plugintype'),
            'plugintag' => $data['plugintag'] ?: trim($INPUT->str('plugintag')),
            'pluginsort' => $data['pluginsort'] ?: strtolower(trim($INPUT->str('pluginsort'))),
            'showall' => $data['showall'] ?: $INPUT->str('showall') == 'yes',
            'includetemplates' => $data['includetemplates'] ?: $INPUT->str('includetemplates') == 'yes',
            'onlyrecent' => false
        ];
        $plugins = $this->hlp->getPlugins($request);

        $type = $request['plugintype'];
        $tag  = $request['plugintag'];
        if ($type > 0) {
            $types = implode(', ', $this->hlp->listtypes($type));
            $header = sprintf($this->getLang('t_availabletype'), $types);
            $urlParam = "plugintype=$type,";
        } elseif ($tag) {
            $header = sprintf($this->getLang('t_availabletagged'), hsc($tag));
            $urlParam = "plugintag=" . rawurlencode($tag) . ',';
        } else {
            $header = $this->getLang('t_availableplugins');
            $urlParam = '';
        }
        $header .= ' (' . count($plugins) . ')';

        $R->section_open(2);
        $R->doc .= '<div class="pluginrepo_table" id="extension__table">';
        $R->doc .= '<h3>' . $header . '</h3>';

        // alpha nav when sorted by plugin name
        if ($request['pluginsort'] == 'p' || $request['pluginsort'] == '^p') {
            $R->doc .= '<div class="alphaNav">' . $this->getLang('t_jumptoplugins') . ' ';
            foreach (range('A', 'Z') as $char) {
                $R->doc .= '<a href="#' . strtolower($char) . '">' . $char . '</a> ';
            }
            $R->doc .= '</div>';
        }

        // show reset link when filtered
        if ($type > 0 || $tag || $request['pluginsort']) {
            $R->doc .= '<div class="resetFilter">';
            $R->doc .= $R->internallink($ID, $this->getLang('t_resetfilter'));
            $R->doc .= '</div>';
        }

        // the main table
        $this->showTable($plugins, $urlParam, $data, $R, $request);

        $R->doc .= '</div>';
        $R->section_close();
        return true;
    }

    /**
     * Output new table with more dense layout
     *
     * @param array $plugins array with data entry per plugin
     * @param string $urlParam
     * @param array $data parsed by handler from syntax, entries used:
     *   'showcompatible' bool
     *   'showscreenshot' bool
     * @param Doku_Renderer_xhtml $R renderer
     * @param array $request partly cleaned request parameters
     *   'pluginsort' string shortcuts only
     */
    public function showTable($plugins, $urlParam, $data, $R, $request)
    {
        global $ID;

        $popmax = $this->hlp->getMaxPopularity($ID);

        $sort = $request['pluginsort'];
        if (str_starts_with($sort, '^')) {
            $sortcol = substr($sort, 1);
            $sortarr = '<span>&uarr;</span>';
        } else {
            $sortcol = $sort;
            $sortarr = '<span>&darr;</span>';
        }

        $R->doc .= '<table class="inline">';

        // table headers
        $urlName = wl($ID, $urlParam . 'pluginsort=' . ($sort == 'p' ? '^p' : 'p') . '#extension__table');
        $urlAuthor = wl($ID, $urlParam . 'pluginsort=' . ($sort == 'a' ? '^a' : 'a') . '#extension__table');
        $urlDate = wl($ID, $urlParam . 'pluginsort=' . ($sort == '^d' ? 'd' : '^d') . '#extension__table');
        $urlPopularity = wl($ID, $urlParam . 'pluginsort=' . ($sort == '^c' ? 'c' : '^c') . '#extension__table');
        $urlCompatibility = wl($ID, $urlParam . 'pluginsort=' . ($sort == '^v' ? 'v' : '^v') . '#extension__table');

        $R->doc .= '<tr>';
        $R->doc .= '<th class="info">'
            . '<a href="' . $urlName . '" title="' . $this->getLang('t_sortname') . '">'
            . ($sortcol == 'p' ? $sortarr : '') . $this->getLang('t_name_' . noNS($ID))
            . '</a>'
            . '<a class="authorSort" href="' . $urlAuthor . '" title="' . $this->getLang('t_sortauthor') . '">'
            . ($sortcol == 'a' ? $sortarr : '') . $this->getLang('t_author')
            . '</a>'
            . '</th>';
        if ($data['showscreenshot']) {
            $R->doc .= '<th class="screenshot">' . $this->getLang('t_screenshot') . '</th>';
        }
        $R->doc .= '<th class="lastupdate">'
            . '<a href="' . $urlDate . '" title="' . $this->getLang('t_sortdate') .  '">'
            . ($sortcol == 'd' ? $sortarr : '') . $this->getLang('t_date')
            . '</a>'
            . '</th>';
        $R->doc .= '<th class="popularity">'
            . '<a href="' . $urlPopularity . '" title="' . $this->getLang('t_sortpopularity') . '">'
            . ($sortcol == 'c' ? $sortarr : '') . $this->getLang('t_popularity')
            . '</a>'
            . '</th>';
        if ($data['showcompatible']) {
            $R->doc .= '<th>'
                . '<a href="' . $urlCompatibility . '" title="' . $this->getLang('t_sortcompatible') . '">'
                .  ($sortcol == 'v' ? $sortarr : '') . $this->getLang('t_compatible')
                . '</a>'
                . '</th>';
        }
        $R->doc .= '</tr>';

        $compatgroup = 'xx9999-99-99';
        $tmpChar = '';
        foreach ($plugins as $row) {
            if (!$data['showcompatible'] && !$sort && $row['bestcompatible'] !== $compatgroup) {
                $R->doc .= '</table>';
                $R->doc .= '<table class="inline">';
                $R->doc .= '<caption>';
                if ($row['bestcompatible']) {
                    $compatible = $this->hlp->cleanCompat($row['bestcompatible']);
                    $label = array_shift($compatible);
                    $R->doc .= $this->hlp->renderCompatibilityHelp(true)
                        . ' <strong>' . $row['bestcompatible'] . ' ' . $label['label'] . '</strong>';
                } else {
                    $R->doc .= $this->getLang('t_oldercompatibility');
                }
                $R->doc .= '</caption>';
                $compatgroup = $row['bestcompatible'];
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td class="info">';

            // add anchor for alphabet navigation
            $firstChar = substr(noNS($row['plugin']), 0, 1);
            $isAlphaSort = $sort == 'p' || $sort == '^p';
            if ($isAlphaSort && $tmpChar !== $firstChar) {
                $R->doc .= '<a id="' . $firstChar . '"></a>';
                $tmpChar = $firstChar;
            }

            $R->doc .= '<div class="mainInfo">';
            // extension name and link
            $R->doc .= '<strong>';
            $R->doc .= $this->hlp->pluginlink($R, $row['plugin'], $row['name']);
            $R->doc .= '</strong>';
            // download
            $isObsolete = in_array($this->hlp->obsoleteTag, $this->hlp->parsetags($row['tags']));
            if (!$row['securityissue'] && !$row['securitywarning'] && !$isObsolete && $row['downloadurl']) {
                $R->doc .= ' <em>';
                $R->doc .= $R->externallink($row['downloadurl'], $this->getLang('t_download'), true);
                $R->doc .= '</em>';
            }
            // description
            $R->doc .= '<p class="description">';
            $R->doc .= hsc($row['description']);
            $R->doc .= '</p>';
            $R->doc .= '</div>';// mainInfo

            // additional info
            $R->doc .= '<dl>';
            $R->doc .= '<dt>' . $this->getLang('t_provides') . ':</dt>';
            $R->doc .= '<dd>' . $this->hlp->listtype($row['type'], $ID) . '</dd>';
            $R->doc .= '<dt>' . $this->getLang('t_tags') . ':</dt>';
            $R->doc .= '<dd>' . $this->hlp->listtags($row['tags'], $ID) . '</dd>';
            $R->doc .= '<dt class="author">' . $this->getLang('t_author') . ':</dt>';
            $R->doc .= '<dd class="author">';
            $R->emaillink($row['email'], $row['author']);
            $R->doc .= '</dd>';
            $R->doc .= '</dl>';

            $R->doc .= '</td>';

            // screenshot
            if ($data['showscreenshot']) {
                $R->doc .= '<td class="screenshot">';
                $val = $row['screenshot'];
                if ($val) {
                    $title = sprintf($this->getLang('screenshot_title'), noNS($row['plugin']));
                    $attr = [
                        'href' => ml($val, '', true, '&'),
                        'class' => 'media',
                        'rel' => 'lightbox',
                        'data-url' => ml($val, '', true, '&'),
                        'title' => $title,
                        'data-caption' => $title,
                    ];
                    $R->doc .= '<a ' . buildAttributes($attr) . '>';
                    $R->doc .= '<img src="' . ml($val, "w=80") . '" alt="" width="80" /></a>';
                }
                $R->doc .= '</td>';
            }

            // last update and popularity (or bundled)
            if (in_array($row['plugin'], $this->hlp->bundled)) {
                $R->doc .= '<td colspan="2" class="bundled"><em>';
                $R->internallink(':bundled', $this->getLang('t_bundled'));
                $R->doc .= '</em></td>';
            } else {
                $R->doc .= '<td class="lastupdate">';
                $R->doc .= hsc($row['lastupdate']);
                $R->doc .= '</td>';
                $R->doc .= '<td class="popularity">';
                $progressCount = $row['popularity'] . '/' . $popmax;
                $progressWidth = 100 * $row['popularity'] / $popmax;
                $R->doc .= '<div class="progress" title="' . $progressCount . '">'
                    . '<div style="width: ' . $progressWidth . '%;">'
                    . '<span>' . $progressCount . '</span>'
                    . '</div>'
                    . '</div>';
                $R->doc .= '</td>';
            }

            // compatibility
            if ($data['showcompatible']) {
                $dokuReleases = $this->hlp->getDokuReleases();

                $R->doc .= '<td class="center">';
                $R->doc .= $row['bestcompatible'] . '<br />';
                $R->doc .= $dokuReleases[$row['bestcompatible']]['label'];
                $R->doc .= '</td>';
            }

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }
}
