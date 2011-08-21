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
        $extensionType = ($type == 32) ? 'template':'plugin';

        $R->doc .= '<div class="pluginrepo_entry">'.NL;

        /* ===== main info ==== */
        $R->doc .= '<div class="mainInfo">'.NL;
        /*
        $R->doc .= '<h4>'.noNS($id).' ';
        $R->doc .= ($type == 32 ? 'template':'plugin');
        $R->doc .= '</h4>';
        */

        $extensionIcon = '<a class="media" href="/'.$extensionType.'s"><img alt="'.$extensionType.'" class="medialeft" src="'.DOKU_BASE.'lib/plugins/pluginrepo/images/dwplugin.png" width="60" height="60" /></a> ';
        $R->doc .= '<p class="description">'.$extensionIcon.hsc($data['description']).'</p>'.NL;
        if ($data['screenshot_img']) {
            $val = $data['screenshot_img'];
            $R->doc .= '<a href="'.ml($val).'" class="media screenshot" rel="lightbox">';
            $R->doc .= '<img src="'.ml($val,"w=220").'" alt="" width="220" /></a>'.NL;
        }
        $R->doc .= '</div>';// mainInfo

        /* ===== meta info ==== */
        $R->doc .= '<div class="metaInfo"><dl>'.NL;
        $target = getNS($ID).'s';

        // last updated
        if(preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])){
            $R->doc .= '<dt>'.$this->getLang('last_updated_on').'</dt>'.NL;
            $R->doc .= '<dd>'.$data['lastupdate'].'</dd>'.NL;
        }
        // type
        if($type && $type != 32){
            $R->doc .= '<dt>'.$this->getLang('provides').'</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listtype($type,$target).'</dd>'.NL;
        }

        if($data['sourcerepo']) {
            $R->doc .= '<dt>'.$this->getLang('sourcerepo').'</dt>'.NL;
            // TODO: should be different link text
            $R->doc .= '<dd><a href="'.hsc($data['sourcerepo']).'">source</a></dd>'.NL;
        }

        if ($rel['conflicts']) {
            $data['conflicts'] .= ','.join(',',$rel['conflicts']);
        }
        if($data['conflicts']){
            $R->doc .= '<dt>'.$this->getLang('conflicts_with').'</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listplugins($data['conflicts'],$R).'</dd>'.NL;
        }
        if($data['depends']){
            $R->doc .= '<dt>'.$this->getLang('requires').'</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listplugins($data['depends'],$R).'</dd>'.NL;
        }

        $R->doc .= '</dl>'.NL;

        $R->doc .= '</div>'.NL;// metaInfo

        /* ===== usage info ==== */
        $R->doc .= '<div class="usageInfo">'.NL;

        /* compatibility */
        $R->doc .= '<div class="compatibility">';
        $R->doc .= '<p class="label">'.$this->hlp->renderCompatibilityHelp().'</p>'.NL;

        if (!$data['compatible']) {
            $R->doc .= '<p class="nothing">';
            $R->doc .= $this->getLang('no_compatibility');
            $R->doc .= '</p>'.NL;

        } else {
            $compatibility = $this->hlp->cleanCompat($data['compatible']);
            $cols = 0;
            $norecentcompat = true;
            $compatrow = '';
            foreach ($this->hlp->dokuReleases as $release) {
                if (++$cols > 4) break;
                $value = 'unknown';// maybe, possibly?
                if (array_key_exists($release['date'], $compatibility)) {
                    $value = 'yes';// compatible?
                    if ($compatibility[$release['date']]['implicit']) {
                        $value = 'probably';
                    }
                    $norecentcompat = false;
                }
                $compatrow .= '<li class="'.$value.'">'.$release['date'].' '.$release['label'];
                $compatrow .= '&nbsp;<strong><span>'.$value.'</span></strong></li>'.NL;
            }

            if (strpos($data['compatible'],'devel') !== false) {
                $R->doc .= '<p>';
                $R->internallink('devel:develonly',$this->getLang('develonly'));
                $R->doc .= '</p>'.NL;

            } elseif ($norecentcompat) {
                $R->doc .= '<p>';
                $R->doc .= $data['compatible'];
                $R->doc .= '</p>'.NL;
            } else {
                $R->doc .= '<div class="versions"><ul>'.NL.$compatrow.'</ul></div>'.NL;
            }
        }
        $R->doc .= '</div>'.NL;// compatibilityInfo

        /* action links (download, bugs, donate) */
        if ($data['downloadurl'] || $data['bugtracker'] || $data['donationurl']) {
            $R->doc .= '<ul class="actions">'.NL;
            /*
            $downloadtext = ($type == 32 ? $this->getLang('downloadurl_tpl') : $this->getLang('downloadurl'));
            $this->getLang('bugtracker')
            $this->getLang('donationurl')
            */
            if($data['downloadurl']) $R->doc .= '<li><a class="download" href="'.hsc($data['downloadurl']).'">Download</a></li>'.NL;
            if($data['bugtracker'])  $R->doc .= '<li><a class="bugs" href="'.hsc($data['bugtracker']).'">Report bugs</a></li>'.NL;
            if($data['donationurl']) $R->doc .= '<li><a class="donate" href="'.hsc($data['donationurl']).'">Donate</a></li>'.NL;
            $R->doc .= '</ul><div class="clearer"></div>'.NL;
        }

        $R->doc .= '</div>'.NL;// usageInfo

        /* ===== more info ==== */
        $hasUnderscoreIssue = (strpos($id,'_') !== false);
        if($rel['similar'] || $data['tags'] || $data['securitywarning'] || $data['securityissue'] || $hasUnderscoreIssue) {
            $R->doc .= '<div class="moreInfo">';

            /* security issues */
            if($data['securitywarning']){

                $R->doc .= '<div class="notify">';
                $securitylink = $R->internallink('devel:security',$this->getLang('securitylink'),NULL,true);
                $R->doc .= '<p><strong>'.sprintf($this->getLang('securitywarning'),$securitylink).'</strong> ';
                if(in_array($data['securitywarning'],$this->hlp->securitywarning)){
                    $R->doc .= $this->getLang('security_'.$data['securitywarning']);
                }else{
                    $R->doc .= hsc($data['securitywarning']);
                }
                $R->doc .= '</p></div>';
            }

            if($data['securityissue']){
                $R->doc .= '<div class="error">';
                $R->doc .= '<p><strong>'.$this->getLang('securityissue').'</strong> ';
                $R->doc .= hsc($data['securityissue']);
                $securitylink = $R->internallink('devel:security',$this->getLang('securitylink'),NULL,true);
                $R->doc .= '</p><p>'.sprintf($this->getLang('securityrecommendation'),$securitylink).'</p>';
                $R->doc .= '</div>';
            }

            if(strpos($id,'_') !== false) {
                $R->doc .= '<div class="info"><p>';
                $R->doc .= $this->getLang('name_underscore');
                $R->doc .= '</p></div>';
            }

            /* similar & tags */
            if ($rel['similar']) {
                $data['similar'] .= ','.join(',',$rel['similar']);
            }
            if($data['similar']){
                $R->doc .= '<p>'.$this->getLang('similar_to').' ';
                $R->doc .= $this->hlp->listplugins($data['similar'],$R).'</p>'.NL;
            }

            if($data['tags']){
                $R->doc .= '<p>'.$this->getLang('tagged_with').' ';
                $R->doc .= $this->hlp->listtags($data['tags'],$target).'</p>'.NL;
            }
            $R->doc .= '</div>';// moreInfo
        }

        // author
        $R->doc .= '<div class="authorInfo">';
        //$R->doc .= $this->getLang('by').' ';
        $R->emaillink($data['email'],$data['author']);
        // TODO: by the same author
        $R->doc .= '</div>'; // authorInfo

        $R->doc .= '</div>'; // pluginrepo_entry
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
