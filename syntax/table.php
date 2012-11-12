<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_pluginrepo_table extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the repository helper plugin
     */
    var $hlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_pluginrepo_table(){
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
        $this->Lexer->addSpecialPattern('~~pluginrepo~~',$mode,'plugin_pluginrepo_table');
        $this->Lexer->addSpecialPattern('----+ *pluginrepo *-+\n.*?\n----+',$mode,'plugin_pluginrepo_table');
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
    function render($format, &$renderer, $data) {
        if($format == 'xhtml') {
            return $this->_showData($renderer,$data);
        }
        return false;
    }

    /**
     * Output table of plugins with filter and navigation
     */
    function _showData(&$R, $data){
        global $ID;

        $R->info['cache'] = false;
        $R->header($this->getLang('t_search_'.noNS($ID)), 2, null);
        $R->section_open(2);

        $R->doc .= '<div class="pluginrepo_table">'.NL;

        // filter and search
        $R->doc .= '<div class="repoFilter">'.NL;
        $this->_showMainSearch($R, $data);
        if (!$data['plugintype']) {
            $this->_showPluginTypeFilter($R, $data);
        }
        $R->doc .= '</div>'.NL;

        // tag cloud
        $R->doc .= '<div class="repoCloud">'.NL;
        $this->_tagcloud($R, $data);
        $R->doc .= '</div>'.NL;

        $R->doc .= '<div class="clearer"></div>'.NL;
        $R->doc .= '</div>'.NL;// pluginrepo_table
        $R->section_close();

        // main table
        $this->_showPluginTable($R, $data);
    }

    /**
     * Output repo table overview/intro and search form
     */
    function _showMainSearch(&$R, $data){
        global $ID;
        if (substr($ID,-1,1) == 's') {
            $searchNS = substr($ID,0,-1);
        } else {
            $searchNS = $ID;
        }

        $R->doc .= '<p>';
        $R->doc .= $this->getLang('t_searchintro_'.noNS($ID));
        $R->doc .= '</p>'.NL;

        $R->doc .= '<form action="'.wl().'" accept-charset="utf-8" class="search" id="dw__search2" method="get"><div class="no">'.NL;
        $R->doc .= '  <input type="hidden" name="do" value="search" />'.NL;
        $R->doc .= '  <input type="hidden" id="dw__ns" name="ns" value="'.$searchNS.'" />'.NL;
        $R->doc .= '  <input type="text" id="qsearch2__in" accesskey="f" name="id" class="edit" />'.NL;
        $R->doc .= '  <input type="submit" value="'.$this->getLang('t_btn_search').'" class="button" title="'.$this->getLang('t_btn_searchtip').'" />'.NL;
        $R->doc .= '  <div id="qsearch2__out" class="ajax_qsearch JSpopup"></div>'.NL;
        $R->doc .= '</div></form>'.NL;
    }

    /**
     * Output plugin TYPE filter selection
     */
    function _showPluginTypeFilter(&$R, $data){
        global $ID;

        $R->doc .= '<h3>';
        $R->doc .= $this->getLang('t_filterbytype');
        $R->doc .= '</h3>'.NL;

        $R->doc .= '<ul class="types">'.NL;
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typesyntax'),$this->hlp->listtype(1,$ID));
        $R->doc .= '</div></li>'.NL;
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typeaction'),$this->hlp->listtype(4,$ID));
        $R->doc .= '</div></li>'.NL;
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typeadmin'),$this->hlp->listtype(2,$ID));
        $R->doc .= '</div></li>'.NL;
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typehelper'),$this->hlp->listtype(16,$ID));
        $R->doc .= '</div></li>'.NL;
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang('t_typerender'),$this->hlp->listtype(8,$ID));
        $R->doc .= '</div></li>'.NL;

        if ($data['includetemplates']) {
            $R->doc .= '<li><div class="li">';
            $R->doc .= sprintf($this->getLang('t_typetemplate'),$this->hlp->listtype(32,$ID));
            $R->doc .= '</div></li>'.NL;
        }
        $R->doc .= '</ul>'.NL;
    }

    /**
     * Output plugin tag filter selection (cloud)
     */
    function _tagcloud(&$R, $data){
        global $ID;

        $R->doc .= '<h3>';
        $R->doc .= $this->getLang('t_filterbytag');
        $R->doc .= '</h3>'.NL;

        $min  = 0;
        $max  = 0;
        $tags = array();
        $cloudmin = 0;
        if (is_numeric($data['cloudmin'])) {
            $cloudmin = (int)$data['cloudmin'];
        }

        $tagData =$this->hlp->getTags($cloudmin,$data);
        // $tagData will be sorted by cnt (descending)
        foreach($tagData as $tag) {
            if ($tag['tag'] == $this->hlp->obsoleteTag) continue; // obsolete plugins are not included in the table
            $tags[$tag['tag']] = $tag['cnt'];
            if(!$max) $max = $tag['cnt'];
            $min = $tag['cnt'];
        }
        $this->_cloud_weight($tags,$min,$max,5);

        ksort($tags);
        if (count($tags) > 0) {
            $R->doc .= '<div class="cloud">'.NL;
            foreach($tags as $tag => $size){
                $R->doc .= '<a href="'.wl($ID,array('plugintag'=>$tag)).'#extension__table" '.
                           'class="wikilink1 cl'.$size.'"'.
                           'title="List all plugins with this tag">'.hsc($tag).'</a> ';
            }
            $R->doc .= '</div>'.NL;
        }
    }

    /**
     * Assign weight group to each tag in supplied array, use $levels groups
     */
    function _cloud_weight(&$tags,$min,$max,$levels){
        // calculate tresholds
        $tresholds = array();
        for($i=0; $i<=$levels; $i++){
            $tresholds[$i] = pow($max - $min + 1, $i/$levels) + $min - 1;
        }

        // assign weights
        foreach($tags as $tag => $cnt){
            foreach($tresholds as $tresh => $val){
                if($cnt <= $val){
                    $tags[$tag] = $tresh;
                    break;
                }
                $tags[$tag] = $levels;
            }
        }
    }

    /**
     * Output plugin table and "jump to A B C.." navigation
     */
    function _showPluginTable(&$R, $data){
        global $ID;

        $plugins = $this->hlp->getPlugins(array_merge($_REQUEST,$data));
        $type = (int) $_REQUEST['plugintype'];
        $tag  = trim($_REQUEST['plugintag']);

        if ($this->hlp->types[$type]) {
            $header = sprintf($this->getLang('t_availabletype'),$this->hlp->types[$type]);
            $linkopt = "plugintype=$type,";
        } elseif ($tag) {
            $header = sprintf($this->getLang('t_availabletagged'),hsc($tag));
            $linkopt = "plugintag=".rawurlencode($tag).',';
        } else {
            $header = $this->getLang('t_availableplugins');
            $linkopt = '';
        }
        $header .= ' ('.count($plugins).')';

        $R->section_open(2);
        $R->doc .= '<div class="pluginrepo_table" id="extension__table">';
        $R->doc .= '<h3>'.$header.'</h3>';

        // alpha nav when sorted by plugin name
        if($_REQUEST['pluginsort'] == 'p' || $_REQUEST['pluginsort'] == '^p') {
            $R->doc .= '<div class="alphaNav">'.$this->getLang('t_jumptoplugins').' ';
            foreach (range('A', 'Z') as $char) {
                $R->doc .= '<a href="#'.strtolower($char).'">'.$char.'</a> ';
            }
            $R->doc .= '</div>'.NL;
        }

        // reset to show all when filtered
        if($type != 0 || $tag || $_REQUEST['pluginsort']) {
            $R->doc .= '<div class="resetFilter">';
            $R->doc .= $R->internallink($ID,$this->getLang('t_resetfilter'));
            $R->doc .= '</div>'.NL;
        }

        // the main table
        if ($data['tablelayout'] != 'old') {
            $this->_newTable($plugins,$linkopt,$data,$R);
        } else {
            // @todo: drop the classic look completely?
            $this->_classicTable($plugins,$linkopt,$data,$R);
        }

        $R->doc .= '</div>'.NL;
        $R->section_close();
        return true;
    }

    /**
     * Output new table with more dense layout
     */
    function _newTable($plugins,$linkopt,$data,$R) {
        global $ID;

        $popmax = $this->hlp->getMaxPopularity($ID);

        $sort = $_REQUEST['pluginsort'];
        if ($sort{0} == '^') {
            $sortcol = substr($sort, 1);
            $sortarr = '<span>&uarr;</span>';
        } else {
            $sortcol = $sort;
            $sortarr = '<span>&darr;</span>';
        }

        $R->doc .= '<table class="inline">'.NL;

        // table headers
        $R->doc .= '<tr>'.NL;
        // @todo: make ugly long lines shorter
        $R->doc .= '<th class="info"><a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='p'?'^p':'p'). '#extension__table').'" title="'.$this->getLang('t_sortname').'">'.  ($sortcol=='p'?$sortarr:'').$this->getLang('t_name_'.noNS($ID)).'</a>';
        $R->doc .= '  <a class="authorSort" href="'.wl($ID,$linkopt.'pluginsort='.($sort=='a'?'^a':'a'). '#extension__table').'" title="'.$this->getLang('t_sortauthor').'">'.($sortcol=='a'?$sortarr:'').$this->getLang('t_author').'</a></th>'.NL;
        if ($data['screenshot'] == 'yes') {
            $R->doc .= '<th class="screenshot">'.$this->getLang('t_screenshot').'</th>'.NL;
        }
        $R->doc .= '  <th class="lastupdate">  <a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='^d'?'d':'^d').'#extension__table').'" title="'.$this->getLang('t_sortdate').  '">'.  ($sortcol=='d'?$sortarr:'').$this->getLang('t_date').'</a></th>'.NL;
        $R->doc .= '  <th class="popularity">  <a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='^c'?'c':'^c').'#extension__table').'" title="'.$this->getLang('t_sortpopularity').'">'.($sortcol=='c'?$sortarr:'').$this->getLang('t_popularity').'</a></th>'.NL;
        if ($data['compatible'] == 'yes') {
            $R->doc .= '  <th><a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='^v'?'v':'^v').'#extension__table').'" title="'.$this->getLang('t_sortcompatible').'">'.  ($sortcol=='v'?$sortarr:'').$this->getLang('t_compatible').'</a></th>'.NL;
        }
        $R->doc .= '</tr>'.NL;

        $compatgroup = 'xx9999-99-99';
        $tmpChar = '';
        foreach($plugins as $row) {
            $id = (getNS($row['plugin']) ? $row['plugin'] : ':plugin:'.$row['plugin']);
            if(!page_exists(cleanID($id))){
                $this->hlp->deletePlugin($row['plugin']);
                continue;
            }

            if (!$data['compatible'] && !$sort && $row['bestcompatible'] !== $compatgroup) {
                $R->doc .= '</table>'.NL;
                $R->doc .= '<table class="inline">'.NL;
                $R->doc .= '<caption>';
                if ($row['bestcompatible']) {
                    $label = array_shift($this->hlp->cleanCompat($row['bestcompatible']));
                    $label = $label['label'];
                    $R->doc .= $this->hlp->renderCompatibilityHelp(true).' <strong>'.$row['bestcompatible'].' '.$label.'</strong>';
                } else {
                    $R->doc .= $this->getLang('t_oldercompatibility');
                }
                $R->doc .= '</caption>'.NL;
                $compatgroup = $row['bestcompatible'];
            }

            $R->doc .= '<tr>'.NL;
            $R->doc .= '<td class="info">'.NL;

            // add anchor for alphabet navigation
            $firstChar = substr(noNS($row['plugin']),0,1);
            $isAlphaSort = ($_REQUEST['pluginsort'] == 'p') || ($_REQUEST['pluginsort'] == '^p');
            if ($isAlphaSort && ($tmpChar!=$firstChar)) {
                $R->doc .= '<a name="'.$firstChar.'"></a>'.NL;
                $tmpChar = $firstChar;
            }

            $R->doc .= '<div class="mainInfo">'.NL;
            // extension name and link
            $R->doc .= '<strong>';
            $R->doc .= $this->hlp->pluginlink($R, $row['plugin'], ucfirst(noNS($row['name'])).($row['type']==32?' template':' plugin'));
            $R->doc .= '</strong>'.NL;
            // download
            if($row['downloadurl'] && !$row['securityissue'] && !$row['securitywarning']){
                $R->doc .= ' <em>';
                $R->doc .= $R->externallink($row['downloadurl'], $this->getLang('t_download'), null, true);
                $R->doc .= '</em>'.NL;
            }
            // description
            $R->doc .= '<p class="description">';
            $R->doc .= hsc($row['description']);
            $R->doc .= '</p>'.NL;
            $R->doc .= '</div>'.NL;// mainInfo

            // additional info
            $R->doc .= '<dl>'.NL;
            $R->doc .= '<dt>'.$this->getLang('t_provides').':</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listtype($row['type'],$ID).'</dd>'.NL;
            $R->doc .= '<dt>'.$this->getLang('t_tags').':</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listtags($row['tags'],$ID).'</dd>'.NL;
            $R->doc .= '<dt class="author">'.$this->getLang('t_author').':</dt>'.NL;
            $R->doc .= '<dd class="author">';
            $R->emaillink($row['email'],$row['author']);
            $R->doc .= '</dd>'.NL;
            $R->doc .= '</dl>'.NL;

            $R->doc .= '</td>'.NL;

            // screenshot
            if ($data['screenshot'] == 'yes') {
                $R->doc .= '<td class="screenshot">';
                $val = $row['screenshot'];
                if ($val) {
                    $title = 'screenshot: '.basename(str_replace(':','/',$val));
                    $R->doc .= '<a href="'.ml($val).'" class="media" rel="lightbox">';
                    $R->doc .= '<img src="'.ml($val,"w=80").'" alt="" width="80" /></a>';
                }
                $R->doc .= '</td>'.NL;
            }

            // last update and popularity (or bundled)
            if(in_array($row['plugin'], $this->hlp->bundled)){
                $R->doc .= '<td colspan="2" class="bundled"><em>';
                $R->internallink(':bundled',$this->getLang('t_bundled'));
                $R->doc .= '</em></td>'.NL;
            }else{
                $R->doc .= '<td class="lastupdate">'.NL;
                $R->doc .= hsc($row['lastupdate']);
                $R->doc .= '</td>'.NL;
                $R->doc .= '<td class="popularity">'.NL;
                $progressCount = $row['popularity'].'/'.$popmax;
                $progressWidth = sprintf(100*$row['popularity']/$popmax);
                $R->doc .= '<div class="progress" title="'.$progressCount.'"><div style="width: '.$progressWidth.'%;"><span>'.$progressCount.'</span></div></div>';
                $R->doc .= '</td>'.NL;
            }

            // compatibility
            if ($data['compatible'] == 'yes') {
                $R->doc .= '<td class="center">'.NL;
                $R->doc .= $row['bestcompatible'].'<br />';
                $R->doc .= $this->hlp->dokuReleases[$row['bestcompatible']]['label'];
                $R->doc .= '</td>'.NL;
            }

            $R->doc .= '</tr>'.NL;
        }
        $R->doc .= '</table>'.NL;
    }

    /**
     * Output classic repository table with only one database field/cell
     */
    function _classicTable($plugins,$linkopt,$data,$R) {
        global $ID;

        $popmax = $this->hlp->getMaxPopularity($ID);

        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th><a href="'.wl($ID,$linkopt.'pluginsort=p#extension__table').'" title="'.$this->getLang('t_sortname').'">'.$this->getLang('t_name').'</a></th>';

        $R->doc .= '<th>'.$this->getLang('t_description').'</th>';
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=a#extension__table').'" title="'.$this->getLang('t_sortauthor').'">'.$this->getLang('t_author').'</a></th>';
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=t#extension__table').'" title="'.$this->getLang('t_sorttype').  '">'.$this->getLang('t_type').'</a></th>';
        if ($data['screenshot'] == 'yes') {
            $R->doc .= '<th>'.$this->getLang('t_screenshot').'</th>';
        }
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=^d#extension__table').'" title="'.$this->getLang('t_sortdate'). '">'.$this->getLang('t_date').'</a></th>';
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=^c#extension__table').'" title="'.$this->getLang('t_sortpopularity').'">'.$this->getLang('t_popularity').'</a></th>';
        $R->doc .= '</tr>';

        foreach($plugins as $row) {
            $id = (getNS($row['plugin']) ? $row['plugin'] : ':plugin:'.$row['plugin']);
            if(!page_exists(cleanID($id))){
                $this->hlp->deletePlugin($row['plugin']);
                continue;
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td>';
            $R->doc .= $this->hlp->pluginlink($R, $row['plugin']);
            $R->doc .= '</td>';
            $R->doc .= '<td>';
            $R->doc .= '<strong>'.hsc($row['name']).'</strong><br />';
            $R->doc .= hsc($row['description']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->emaillink($row['email'],$row['author']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= $this->hlp->listtype($row['type'],$ID);
            $R->doc .= '</td>';

            if ($data['screenshot'] == 'yes') {
                $R->doc .= '<td>';
                $val = $row['screenshot'];
                if ($val) {
                    $title = 'screenshot: '.basename(str_replace(':','/',$val));
                    $R->doc .= '<a href="'.ml($val).'" class="media" rel="lightbox">';
                    $R->doc .= '<img src="'.ml($val,"w=80").'" alt="'.hsc($title).'" width="80"/></a>';
                }
                $R->doc .= '</td>';
            }

            if(in_array($row['plugin'], $this->hlp->bundled)){
                $R->doc .= '<td></td><td><i>'.$this->getLang('t_bundled').'</i></td>';
            }else{
                $R->doc .= '<td>';
                $R->doc .= hsc($row['lastupdate']);
                $R->doc .= '</td><td>';
                $R->doc .= '<div class="prog-border" title="'.$row['popularity'].'/'.$popmax.'"><div class="prog-bar" style="width: '.sprintf(100*$row['popularity']/$popmax).'%;"></div></div>';
                $R->doc .= '</td>';
            }

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }

}

