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
     * will hold the data helper plugin
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
     * Override getLang to be able to select language by namespace
     */
    function getLang($langcode,$id) {
        return $this->hlp->getLang($langcode,$id);
    }

    /**
     * Output table of plugins with filter and navigation
     */
    function _showData(&$R, $data){
        global $ID;
        $lang = split(":",getNS($ID),2);
        $lang =(count($lang) == 2 ? $lang[0] : 'en');

        $R->info['cache'] = false;

        $R->header($this->getLang($lang,'t_searchplugins'), 2, null);
        $R->section_open(2);

        $R->doc .= '<div id="pluginrepo__repo">';

        $R->doc .= '<div class="repo_info">';
        $this->_showMainSearch(&$R, $lang, $data);
        if (!$data['plugintype']) {
            $this->_showPluginTypeFilter(&$R, $lang, $data);
        }
        $R->doc .= '</div>';

        $R->doc .= '<div class="repo_cloud">';
        $this->_tagcloud($R, $lang, $data);
        $R->doc .= '</div>'.DOKU_LF;

        $R->doc .= '</div>';
        $R->doc .= '<div class="clearer"></div>';
        $R->section_close();

        $this->_showPluginTable(&$R, $lang, $data);
    }

    /**
     * Output repo table overview/intro and search form 
     */
    function _showMainSearch(&$R, $lang, $data){
        $R->doc .= '<p>';
        $R->doc .= $this->getLang($lang,'t_searchintro');
        $R->doc .= '<p>';

        $R->doc .= '<div id="repo_searchform">';
        $R->doc .= '<form action="'.wl().'" accept-charset="utf-8" class="search" id="dw__search2" method="get"><div class="no">';
        $R->doc .= '<input type="hidden" name="do" value="search" />';
        $R->doc .= '<input type="text" id="qsearch2__in" accesskey="f" name="id" class="edit" />';
        $R->doc .= '<input type="submit" value="'.$this->getLang($lang,'t_btn_search').'" class="button" title="'.$this->getLang($lang,'t_btn_searchtip').'" />';
        $R->doc .= '<div id="qsearch2__out" class="ajax_qsearch JSpopup"></div>';
        $R->doc .= '</div></form>';
        $R->doc .= '</div>'.DOKU_LF;
        $R->doc .= '<div class="clearer"></div>';
    }

    /**
     * Output plugin TYPE filter selection
     */
    function _showPluginTypeFilter(&$R, $lang, $data){
        global $ID;

        $R->doc .= '<h3>';
        $R->doc .= $this->getLang($lang,'t_filterbytype');
        $R->doc .= '</h3>';

        $R->doc .= '<ul><li><div class="li">';
        $R->doc .= sprintf($this->getLang($lang,'t_typesyntax'),$this->hlp->listtype(1,$ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang($lang,'t_typeaction'),$this->hlp->listtype(4,$ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang($lang,'t_typeadmin'),$this->hlp->listtype(2,$ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang($lang,'t_typehelper'),$this->hlp->listtype(16,$ID));
        $R->doc .= '</div></li>';
        $R->doc .= '<li><div class="li">';
        $R->doc .= sprintf($this->getLang($lang,'t_typerender'),$this->hlp->listtype(8,$ID));
        $R->doc .= '</div></li>';

        if ($data['includetemplates']) {
            $R->doc .= '<li><div class="li">';
            $R->doc .= sprintf($this->getLang($lang,'t_typetemplate'),$this->hlp->listtype(32,$ID));
            $R->doc .= '</div></li>';
        }
        $R->doc .= '</ul>'.DOKU_LF;
    }

    /**
     * Output plugin tag filter selection (cloud)
     */
    function _tagcloud(&$R, $lang, $data){
        global $ID;
        
        $R->doc .= '<h3>';
        $R->doc .= $this->getLang($lang,'t_filterbytag');
        $R->doc .= '</h3>';
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
            if ($tag['A.tag'] == $this->hlp->obsoleteTag) continue;
            $tags[$tag['A.tag']] = $tag['cnt'];
            if(!$max) $max = $tag['cnt'];
            $min = $tag['cnt'];
        }
        $this->_cloud_weight($tags,$min,$max,5);

        ksort($tags);
        foreach($tags as $tag => $size){
            $R->doc .= '<a href="'.wl($ID,array('plugintag'=>$tag)).'#repotable" '.
                       'class="wikilink1 cl'.$size.'"'.
                       'title="List all plugins with this tag">'.hsc($tag).'</a> ';
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
    function _showPluginTable(&$R, $lang, $data){
        global $ID;

        $plugins = $this->hlp->getPlugins(array_merge($_REQUEST,$data));
        $type = (int) $_REQUEST['plugintype'];
        $tag  = trim($_REQUEST['plugintag']);

        if ($this->hlp->types[$type]) {
            $header = sprintf($this->getLang($lang,'t_availabletype'),$this->hlp->types[$type]);
            $linkopt = "plugintype=$type,";
        } elseif ($tag) {
            $header = sprintf($this->getLang($lang,'t_availabletagged'),hsc($tag));
            $linkopt = "plugintag=".rawurlencode($tag).',';
        } else {
            $header = $this->getLang($lang,'t_availableplugins');
            $linkopt = '';
        }
        $header .= ' ('.count($plugins).')';

        $R->section_open(2);
        $R->doc .= '<div id="pluginrepo__table">';
        $R->doc .= '<a name="repotable" />';
        $R->doc .= '<h3>'.$header.'</h3>';

        if($_REQUEST['pluginsort'] == 'p' || $_REQUEST['pluginsort'] == '^p') {
            $R->doc .= '<div class="repo__alphabet">'.$this->getLang($lang,'t_jumptoplugins').' ';
            foreach (range('A', 'Z') as $char) {
                $R->doc .= '<a href="#'.strtolower($char).'">'.$char.'</a> ';
            }
            $R->doc .= '</div>';
        }

        if($type != 0 || $tag) {
            $R->doc .= '<div class="repo__resetfilter">';
            $R->doc .= $R->internallink($ID,$this->getLang($lang,'t_resetfilter'));
            $R->doc .= '</div>';
        }
        $R->doc .= '<div class="clearer"></div>';

        if ($data['tablelayout'] != 'old') {
            $this->_newTable($plugins,$linkopt,$data,$lang,$R);
        } else {
            $this->_classicTable($plugins,$linkopt,$data,$lang,$R);
        }

        $R->doc .= '</div>';
        $R->section_close();
        return true;
    }

    /**
     * Output new table with more dense layout
     */
    function _newTable($plugins,$linkopt,$data,$lang,$R) {
        global $ID;

        $popmax = $this->hlp->getMaxPopularity();
        $allcnt = $this->hlp->getPopularitySubmitters();

        $sort = $_REQUEST['pluginsort'];
        if ($sort{0} == '^') {
            $sortcol = substr($sort, 1);
            $sortarr = '<span>&uarr;</span>';
        } else {
            $sortcol = $sort;
            $sortarr = '<span>&darr;</span>';
        }

        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th><a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='p'?'^p':'p'). '#repotable').'" title="'.$this->getLang($lang,'t_sortname').'">'.  ($sortcol=='p'?$sortarr:'').$this->getLang($lang,'t_name').'</a>';
        $R->doc .= '        <div class="repo_authorsort">
                            <a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='a'?'^a':'a'). '#repotable').'" title="'.$this->getLang($lang,'t_sortauthor').'">'.($sortcol=='a'?$sortarr:'').$this->getLang($lang,'t_author').'</a></div></th>';
        if ($data['screenshot'] == 'yes') {
            $R->doc .= '<th class="screenshot">'.$this->getLang($lang,'t_screenshot').'</th>';
        }
        $R->doc .= '  <th class="lastupdate">  <a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='^d'?'d':'^d').'#repotable').'" title="'.$this->getLang($lang,'t_sortdate').  '">'.  ($sortcol=='d'?$sortarr:'').$this->getLang($lang,'t_date').'</a></th>';
        $R->doc .= '  <th class="popularity">  <a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='^c'?'c':'^c').'#repotable').'" title="'.$this->getLang($lang,'t_sortpopularity').'">'.($sortcol=='c'?$sortarr:'').$this->getLang($lang,'t_popularity').'</a></th>';
        if ($data['compatible'] == 'yes') {
            $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort='.($sort=='^v'?'v':'^v').'#repotable').'" title="'.$this->getLang($lang,'t_sortcompatible').'">'.  ($sortcol=='v'?$sortarr:'').$this->getLang($lang,'t_compatible').'</a></th>';
        }
        $R->doc .= '</tr>';

        $compatgroup = '9999-99-99';
        foreach($plugins as $row) {
            $link = $this->hlp->pluginlink($R, $row['A.plugin'], ucfirst(noNS($row['A.plugin'])).($row['A.type']==32?' template':' plugin'));
            if(strpos($link,'class="wikilink2"')){
                $this->hlp->deletePlugin($row['A.plugin']);
                continue;
            }

            if (!$data['compatible'] && !$sort && $row['A.bestcompatible'] !== $compatgroup) {
                $R->doc .= '</table>';
                $R->doc .= $this->getLang($lang,'compatible_with').' <b>'.($row['A.bestcompatible']?$row['A.bestcompatible']:'older versions').'</b>';
                $R->doc .= '<table class="inline">';
                $compatgroup = $row['A.bestcompatible'];
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td>';
            $R->doc .= '<a name="'.substr($row['A.plugin'],0,1).'"></a>';

            $R->doc .= '<div class="repo_plugintitle">';
            $R->doc .= $link;
            $R->doc .= '</div>';
            if($row['A.downloadurl'] && !$row['A.securityissue'] && !$row['A.securitywarning']){
                $R->doc .= '<div class="repo_download">';
                $R->doc .= $R->externallink($row['A.downloadurl'], $this->getLang($lang,'t_download'), null, true);
                $R->doc .= '</div>';
            }
            $R->doc .= '<div class="clearer"></div><div class="details">';
            $R->doc .= hsc($row['A.description']).'<br />';

            $R->doc .= '<div class="repo_provides">'.$this->getLang($lang,'t_provides').': ';
            $R->doc .= $this->hlp->listtype($row['A.type'],$ID);
            $R->doc .= ' '.$this->getLang($lang,'t_tags').': ';
            $R->doc .= $this->hlp->listtags($row['A.tags'],$ID);
            $R->doc .= '</div></div>';

            $R->doc .= '<div class="repo_mail">'.$this->getLang($lang,'t_author').': ';
            $R->emaillink($row['A.email'],$row['A.author']);
            $R->doc .= '</div>';
            $R->doc .= '</td>';

            if ($data['screenshot'] == 'yes') {
                $R->doc .= '<td class="screenshot">';
                $val = $row['A.screenshot'];
                if ($val) {
                    $title = 'screenshot: '.basename(str_replace(':','/',$val));
                    $R->doc .= '<a href="'.ml($val).'" class="media" rel="lightbox">';
                    $R->doc .= '<img src="'.ml($val,"w=80").'" alt="'.hsc($title).'" width="80"/></a>';
                }
                $R->doc .= '</td>';
            }

            if(strpos($this->getConf('bundled'),$row['A.plugin']) === false){
                $R->doc .= '<td class="lastupdate">';
                $R->doc .= hsc($row['A.lastupdate']);
                $R->doc .= '</td><td class="popularity">';
                $R->doc .= '<div class="prog-border" title="'.$row['cnt'].'/'.$allcnt.'"><div class="prog-bar" style="width: '.sprintf(100*$row['cnt']/$popmax).'%;"></div></div>';
                $R->doc .= '</td>';
            }else{
                $R->doc .= '<td colspan="2" class="center"><i>';
                $R->internallink(':bundled',$this->getLang($lang,'t_bundled'));
                $R->doc .= '</i></td>';
            }

            if ($data['compatible'] == 'yes') {
                $R->doc .= '<td class="center">';
                $R->doc .= $row['A.bestcompatible'].'<br />';
                $R->doc .= $this->hlp->dokuReleases[$row['A.bestcompatible']]['label'];
                $R->doc .= '</td>';
            }

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }

    /**
     * Output classic repository table with only one database field/cell
     */
    function _classicTable($plugins,$linkopt,$data,$lang,$R) {
        global $ID;

        $popmax = $this->hlp->getMaxPopularity();
        $allcnt = $this->hlp->getPopularitySubmitters();

        $R->doc .= '<table class="inline">';
        $R->doc .= '<tr><th><a href="'.wl($ID,$linkopt.'pluginsort=p#repotable').'" title="'.$this->getLang($lang,'t_sortname').'">'.$this->getLang($lang,'t_name').'</a></th>';

        $R->doc .= '<th>'.$this->getLang($lang,'t_description').'</th>';
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=a#repotable').'" title="'.$this->getLang($lang,'t_sortauthor').'">'.$this->getLang($lang,'t_author').'</a></th>';
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=t#repotable').'" title="'.$this->getLang($lang,'t_sorttype').  '">'.$this->getLang($lang,'t_type').'</a></th>';
        if ($data['screenshot'] == 'yes') {
            $R->doc .= '<th>'.$this->getLang($lang,'t_screenshot').'</th>';
        }
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=^d#repotable').'" title="'.$this->getLang($lang,'t_sortdate'). '">'.$this->getLang($lang,'t_date').'</a></th>';
        $R->doc .= '<th><a href="'.wl($ID,$linkopt.'pluginsort=^c#repotable').'" title="'.$this->getLang($lang,'t_sortpopularity').'">'.$this->getLang($lang,'t_popularity').'</a></th>';
        $R->doc .= '</tr>';

        foreach($plugins as $row) {
            $link = $this->hlp->pluginlink($R, $row['A.plugin']);
            if(strpos($link,'class="wikilink2"')){
                $this->hlp->deletePlugin($row['A.plugin']);
                continue;
            }

            $R->doc .= '<tr>';
            $R->doc .= '<td>';
            $R->doc .= $link;
            $R->doc .= '</td>';
            $R->doc .= '<td>';
            $R->doc .= '<strong>'.hsc($row['A.name']).'</strong><br />';
            $R->doc .= hsc($row['A.description']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->emaillink($row['A.email'],$row['A.author']);
            $R->doc .= '</td>';

            $R->doc .= '<td>';
            $R->doc .= $this->hlp->listtype($row['A.type'],$ID);
            $R->doc .= '</td>';

            if ($data['screenshot'] == 'yes') {
                $R->doc .= '<td>';
                $val = $row['A.screenshot'];
                if ($val) {
                    $title = 'screenshot: '.basename(str_replace(':','/',$val));
                    $R->doc .= '<a href="'.ml($val).'" class="media" rel="lightbox">';
                    $R->doc .= '<img src="'.ml($val,"w=80").'" alt="'.hsc($title).'" width="80"/></a>';
                }
                $R->doc .= '</td>';
            }

            if(strpos($this->getConf('bundled'),$row['A.plugin']) === false){
                $R->doc .= '<td>';
                $R->doc .= hsc($row['A.lastupdate']);
                $R->doc .= '</td><td>';
                $R->doc .= '<div class="prog-border" title="'.$row['cnt'].'/'.$allcnt.'"><div class="prog-bar" style="width: '.sprintf(100*$row['cnt']/$popmax).'%;"></div></div>';
                $R->doc .= '</td>';
            }else{
                $R->doc .= '<td></td><td><i>'.$this->getLang($lang,'t_bundled').'</i></td>';
            }

            $R->doc .= '</tr>';
        }
        $R->doc .= '</table>';
    }

}

