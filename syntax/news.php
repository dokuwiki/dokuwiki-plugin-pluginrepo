<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
/**
 * Class syntax_plugin_pluginrepo_news
 */
class syntax_plugin_pluginrepo_news extends SyntaxPlugin
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
        if ($this->hlp === null) {
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
        $this->Lexer->addSpecialPattern('----+ *pluginnews *-+\n.*?\n----+', $mode, 'plugin_pluginrepo_news');
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
            'headline' => '',
            'style' => '',
            'link' => '',
            'linktext' => '',
            'entries' => 0,
            'random' => true,
            'showscreenshot' => false,
            //filter
            'plugins' => '',
            'plugintype' => 0,
            'plugintag' => '',
            'pluginsort' => '',
            'showall' => false,
            'includetemplates' => false,
            'onlyrecent' => true
        ];
        return $this->hlp->parseData($match, $initialData);
    }

    /**
     * Create output
     *
     * @param string $format output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array $data data created by handler() used entries:
     *          headline: headline of new block
     *          link: link shown at the bottom of the news block
     *          linktext: text for the link
     *          style: 'sameauthor' shows extensions of the same author (only on extension page), otherwise random pick
     *        ..more see functions below
     * @return  boolean rendered correctly? (however, returned value is not used at the moment)
     * @throws Exception
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format != 'xhtml') {
            return false;
        }
        /** @var Doku_Renderer_xhtml $renderer */

        $renderer->doc .= '<div class="pluginrepo_news">';
        $renderer->doc .= '<h4>' . hsc($data['headline']) . '</h4>';

        switch ($data['style']) {
            case 'sameauthor':
                $this->showSameAuthor($renderer, $data);
                break;
            default:
                $this->showDefault($renderer, $data);
        }

        if ($data['link']) {
            $renderer->doc .= '<p class="more">';
            $renderer->internallink($data['link'], $data['linktext']);
            $renderer->doc .= '</p>';
        }
        $renderer->doc .= '</div>';
        return true;
    }

    /**
     * Output html for showing a list of plugins/templates of same author
     *
     * @param Doku_Renderer_xhtml $R
     * @param array $data used entries:
     *  <ul>
     *      <li>entries: number of plugins/templates displayed, otherwise 10</li>
     *  </ul>
     */
    public function showSameAuthor($R, $data)
    {
        global $ID;

        if (curNS($ID) == 'plugin') {
            $id = noNS($ID);
        } else {
            $id = curNS($ID) . ':' . noNS($ID);
        }

        $rel = $this->hlp->getPluginRelations($id);
        if (count($rel['sameauthor']) == 0) {
            $R->doc .= '<p class="nothing">Can\'t find any other plugins or templates</p>';
            return;
        }

        $limit = $data['entries'] > 0 ? $data['entries'] : 10;
        $i = 0;
        $R->doc .= '<ul>';
        while ($i < count($rel['sameauthor']) && $i < $limit) {
            $R->doc .= '<li>' . $this->hlp->pluginlink($R, $rel['sameauthor'][$i++]) . '</li>';
        }
        $R->doc .= '</ul>';
    }

    /**
     * Output html for showing plugins/templates (eventually randomly)
     *
     * @param Doku_Renderer_xhtml $R
     * @param array $data used entries:
     *  <ul>
     *      <li>entries: number of plugins/templates displayed, otherwise 1</li>
     *      <li>random: if 'no' the plugin/template is not selected randomly</li>
     *      <li>screenshot: if 'yes' a screenshot is shown</li>
     *      <li>via getPlugins():
     *          <ul>
     *              <li>'plugins' array or str, if used plugintype and plugintag are skipped</li>
     *              <li>'plugintype' int,</li>
     *              <li>'plugintag' str</li>
     *              <li>'pluginsort' str shortcuts assumed</li>
     *              <li>'showall' bool</li>
     *              <li>'includetemplates' bool</li>
     *              <li>'onlyrecent' bool</li>
     *          </ul>
     *      </li>
     *  </ul>
     * @throws Exception
     * @see helper_plugin_pluginrepo_repository::getPlugins()
     */
    public function showDefault($R, $data)
    {
        $plugins = $this->hlp->getPlugins($data);

        $limit = $data['entries'] > 0 ? $data['entries'] : 1;
        $limit = min($limit, count($plugins));

        if ($data['random']) {
            $start = random_int(0, count($plugins) - $limit);
        } else {
            $start = 0;
        }

        for ($i = 0; $i < $limit; $i++) {
            $row = $plugins[$start + $i];
            $linkText = ucfirst(noNS($row['plugin'])) . ($row['type'] == 32 ? ' template' : ' plugin');
            $R->doc .= '<p class="title">' . $this->hlp->pluginlink($R, $row['plugin'], $linkText) . '</p>';
            $R->doc .= '<p class="description">' . $row['description'] . '</p>';

            $url = $row['screenshot'];
            if ($url && $data['showscreenshot']) {
                $attr = [
                    'href' => ml($url, '', true, '&'),
                    'class' => 'media screenshot',
                    'rel' => 'lightbox',
                    'data-url' => ml($url, '', true, '&'),
                ];

                $R->doc .= '<a ' . buildAttributes($attr) . '>'
                    . '<img src="' . ml($url, "w=200") . '" alt="" width="200" />'
                    . '</a>';
            }

            $R->doc .= '<p class="author">Author: ';
            $R->emaillink($row['email'], $row['author']);
            $R->doc .= '</p>';
        }
    }
}
