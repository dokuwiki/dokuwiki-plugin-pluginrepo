<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_pluginrepo_entry extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the repository helper plugin
     */
    var $hlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_pluginrepo_entry(){
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
     * Connect pattern to lexer (actual pattern used doesn't matter, namespace controls plugin type)
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *plugin *-+\n.*?\n----+',$mode,'plugin_pluginrepo_entry');
        $this->Lexer->addSpecialPattern('----+ *template *-+\n.*?\n----+',$mode,'plugin_pluginrepo_entry');
    }

    /**
     * Handle the match - parse the data
     */
    function handle($match, $state, $pos, &$handler){
        global $ID;

        $data = $this->hlp->parseData($match);
        if (curNS($ID) == 'template') {
            $data['type'] = 'template';
        }
        return $data;
    }

    /**
     * Create output or save the data
     */
    function render($format, &$renderer, $data) {
        global $ID;

        if (curNS($ID) == 'plugin') {
            $id = noNS($ID);
        } else {
            $id = curNS($ID).':'.noNS($ID);
        }

        switch ($format){
            case 'xhtml':
                $this->_showData($data,$id,$renderer);
                return true;
            case 'metadata':
                // only save if in first level namespace to ignore translated namespaces
                if(substr_count($ID,':') == 1){
                    $this->_saveData($data,$id,$renderer->meta['title']);
                }
                return true;
            default:
                return false;
        }
    }

    /**
     * Output the data in a table
     */
    function _showData($data,$id,&$R){
        global $ID;

        $rel = $this->hlp->getPluginRelations($id);
        $type = $this->hlp->parsetype($data['type']);

        if (!$data['screenshot_img']) {
            $R->doc .= '<div id="pluginrepo__plugin" class="pluginrepo__box">';

        } else {
            $R->doc .= '<div id="pluginrepo__plugin" class="pluginrepo__screenshotbox">';

            $val = $data['screenshot_img'];
            $title = 'screenshot: '.basename(str_replace(':','/',$val));
            $R->doc .= '<div id="pluginrepo__pluginscreenshot">';
            $R->doc .= '<a href="'.ml($val).'" class="media" rel="lightbox">';
            $R->doc .= '<img src="'.ml($val,"w=190").'" alt="'.hsc($title).'" title="'.hsc($title).'" width="190" />';
            $R->doc .= '</a></div>';
        }

        $R->doc .= '<div>';
        $R->doc .= '<p><strong>'.noNS($id).' ';
        $R->doc .= ($type == 32 ? 'template':'plugin');
        $R->doc .= '</strong> '.$this->getLang('by').' ';
        $R->emaillink($data['email'],$data['author']);
        $R->doc .= '<br />'.hsc($data['description']).'</p>';

        if(preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])){
            $R->doc .= '<span class="lastupd">';
            $R->doc .= $this->getLang('last_updated_on');
            $R->doc .= ' <em>'.$data['lastupdate'].'</em>.</span> ';
        }

        $target = getNS($ID).'s';
        if($type && $type != 32){
            $R->doc .= '<span class="type">';
            $R->doc .= $this->getLang('provides');
            $R->doc .= ' <em>'.$this->hlp->listtype($type,$target).'</em>.</span>';
        }

        $R->doc .= '<table class="inline compatible">';
        $R->doc .= '<tr><th colspan="4">';
        $R->doc .= $this->hlp->renderCompatibilityHelp();
        $R->doc .= '</th></tr>';

        if (!$data['compatible']) {
            $R->doc .= '<tr><td class="compatible__msg" colspan="'.$this->getConf('showcompat').'">';
            $R->doc .= $this->getLang('no_compatibility');
            $R->doc .= '</td></tr>';

        } else {
            $compatibility = $this->hlp->cleanCompat($data['compatible']);
            $cols = 0;
            $norecentcompat = true;
            foreach ($this->hlp->dokuReleases as $release) {
                if (++$cols > 4) break;
                $value = 'unkown_compatible';
                if (array_key_exists($release['date'], $compatibility)) {
                    $value = 'compatible';
                    if ($compatibility[$release['date']]['implicit']) {
                        $value = 'implicitcompatible';
                    }
                    $norecentcompat = false;
                }
                $compatrow = '<td><div class="'.$value.'">'.$release['date'].'<br />'.$release['label'].'</div></td>'.$compatrow;
            }

            if (strpos($data['compatible'],'devel') !== false) {
                $R->doc .= '<tr><td class="compatible__msg" colspan="'.$this->getConf('showcompat').'">';
                $R->internallink('devel:develonly',$this->getLang('develonly'));
                $R->doc .= '</td></tr>';

            } elseif ($norecentcompat) {
                $R->doc .= '<tr><td class="compatible__msg" colspan="'.$this->getConf('showcompat').'">';
                $R->doc .= $data['compatible'].'<br />';
                $R->doc .= '</td></tr>';
            } else {
                $R->doc .= '<tr>'.$compatrow.'</tr>';
            }
        }
        $R->doc .= '</table>'; // end of compatibility table
        $R->doc .= '</div>';

        $R->doc .= '<p>';
        if ($rel['conflicts']) {
            $data['conflicts'] .= ','.join(',',$rel['conflicts']);
        }
        if($data['conflicts']){
            $R->doc .= '<span class="conflicts">';
            $R->doc .= $this->getLang('conflicts_with');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listplugins($data['conflicts'],$R);
            $R->doc .= '</em>!</span><br />';
        }
        if($data['depends']){
            $R->doc .= '<span class="depends">';
            $R->doc .= $this->getLang('requires');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listplugins($data['depends'],$R);
            $R->doc .= '</em>.</span><br />';
        }

        if ($rel['similar']) {
            $data['similar'] .= ','.join(',',$rel['similar']);
        }
        if($data['similar']){
            $R->doc .= '<span class="similar">';
            $R->doc .= $this->getLang('similar_to');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listplugins($data['similar'],$R);
            $R->doc .= '</em>.</span><br />';
        }
        $R->doc .= '</p>';

        $R->doc .= '<p>';
        if($data['tags']){
            $R->doc .= '<span class="tags">';
            $R->doc .= $this->getLang('tagged_with');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listtags($data['tags'],$target);
            $R->doc .= '</em>.</span><br />';
        }
        $R->doc .= '</p>';

        if($data['securitywarning']){
            $R->doc .= '<p class="securitywarning">';
            $securitylink = $R->internallink('devel:security',$this->getLang('securitylink'),NULL,true);
            $R->doc .= '<b>'.sprintf($this->getLang('securitywarning'),$securitylink).'</b><br /><br />';
            if(in_array($data['securitywarning'],$this->hlp->securitywarning)){
                $R->doc .= '<i>'.$this->getLang('security_'.$data['securitywarning']).'</i>';
            }else{
                $R->doc .= '<i>'.hsc($data['securitywarning']).'</i>';
            }
            $R->doc .= '</p>';
        }

        if($data['securityissue']){
            $R->doc .= '<p class="security">';
            $R->doc .= '<b>'.$this->getLang('securityissue').'</b><br /><br />';
            $R->doc .= '<i>'.hsc($data['securityissue']).'</i><br /><br />';
            $securitylink = $R->internallink('devel:security',$this->getLang('securitylink'),NULL,true);
            $R->doc .= sprintf($this->getLang('securityrecommendation'),$securitylink);
            $R->doc .= '.</p>';
        }

        if(strpos($id,'_') !== false) {
            $R->doc .= '<p class="security">';
            $R->doc .= '<b>'.$this->getLang('name_underscore').'</b>';
            $R->doc .= '</p>';
        }

        $R->doc .= '</div>';

        // add tabs
        $R->doc .= '<ul id="pluginrepo__foldout">';
        $downloadtext = ($type == 32 ? $this->getLang('downloadurl_tpl') : $this->getLang('downloadurl'));
        if($data['downloadurl']) $R->doc .= '<li><a class="download" href="'.hsc($data['downloadurl']).'">'.$downloadtext.'</a></li>';
        if($data['bugtracker'])  $R->doc .= '<li><a class="bugs" href="'.hsc($data['bugtracker']).'">'.$this->getLang('bugtracker').'</a></li>';
        if($data['sourcerepo'])  $R->doc .= '<li><a class="repo" href="'.hsc($data['sourcerepo']).'">'.$this->getLang('sourcerepo').'</a></li>';
        if($data['donationurl']) $R->doc .= '<li><a class="donate" href="'.hsc($data['donationurl']).'">'.$this->getLang('donationurl').'</a></li>';
        $R->doc .= '</ul>';
    }

    /**
     * Save date to the database
     */
    function _saveData($data,$id,$name){
        $db = $this->hlp->_getPluginsDB();
        if (!$db) return;

        if (!$name) $name = $id;
        if (!preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])) {
            $data['lastupdate'] = null;
        } else {
            $data['lastupdate'] = $data['lastupdate'];
        }

        if (in_array($id, $this->hlp->bundled)) {
            $compatible = 'xx9999-99-99';
        } else {
            $compatible = array_shift(array_keys($this->hlp->cleanCompat($data['compatible'])));
        }

        $type = $this->hlp->parsetype($data['type']);

        // handle securityissue, tags field NOT NULL otherwise WHERE clauses will fail
        if (!$data['securityissue']) $data['securityissue'] = "";
        if (!$data['tags']) $data['tags'] = "";

        $stmt = $db->prepare('REPLACE INTO plugins
                               (plugin, name, description,
                                author, email,
                                compatible, bestcompatible, lastupdate, securityissue, securitywarning,
                                downloadurl, bugtracker, sourcerepo, donationurl,
                                screenshot, tags, type)
                              VALUES
                               (:plugin, :name, :description,
                                :author, LOWER(:email),
                                :compatible, :bestcompatible, :lastupdate, :securityissue, :securitywarning,
                                :downloadurl, :bugtracker, :sourcerepo, :donationurl,
                                :screenshot, :tags, :type) ');
        $stmt->execute(array(':plugin' =>  $id,
                             ':name' => $name,
                             ':description' => $data['description'],
                             ':author' => $data['author'],
                             ':email' => $data['email'],
                             ':compatible' => $data['compatible'],
                             ':bestcompatible' => $compatible,
                             ':lastupdate' => $data['lastupdate'],
                             ':securityissue' => $data['securityissue'],
                             ':securitywarning' => $data['securitywarning'],
                             ':downloadurl' => $data['downloadurl'],
                             ':bugtracker' => $data['bugtracker'],
                             ':sourcerepo' => $data['sourcerepo'],
                             ':donationurl' => $data['donationurl'],
                             ':screenshot' => $data['screenshot_img'],
                             ':tags' => $data['tags'],
                             ':type' => $type));

        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $insert = 'INSERT IGNORE';
        } else {
            $insert = 'INSERT OR IGNORE';
        }

        $tags = $this->hlp->parsetags($data['tags']);
        $stmt = $db->prepare('DELETE FROM plugin_tags WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($tags as $tag){
            $stmt = $db->prepare($insert.' INTO plugin_tags (plugin, tag) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$tag));
        }

        $deps = explode(',',$data['depends']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $stmt = $db->prepare('DELETE FROM plugin_depends WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($deps as $dep){
            $stmt = $db->prepare($insert.' INTO plugin_depends (plugin, other) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$dep));
        }

        $deps = explode(',',$data['conflicts']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $stmt = $db->prepare('DELETE FROM plugin_conflicts WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($deps as $dep){
            $stmt = $db->prepare($insert.' INTO plugin_conflicts (plugin, other) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$dep));
        }

        $deps = explode(',',$data['similar']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $stmt = $db->prepare('DELETE FROM plugin_similar WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($deps as $dep){
            $stmt = $db->prepare($insert.' INTO plugin_similar (plugin, other) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$dep));
        }
    }
}
