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
     * @var $hlp helper_plugin_pluginrepo
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
        $hasUnderscoreIssue = (strpos($id,'_') !== false);

        $R->doc .= '<div class="pluginrepo_entry">'.NL;

        $R->doc .= '<div class="usageInfo">'.NL;
        $this->_showCompatibility($R, $data);
        $this->_showActionLinks($R, $data);
        $R->doc .= '</div>'.NL;

        $this->_showMainInfo($R, $data, $extensionType);
        $this->_showMetaInfo($R, $data, $type, $rel);

        if($rel['similar'] || $data['tags'] || $data['securitywarning'] || $data['securityissue'] || $hasUnderscoreIssue) {
            $R->doc .= '<div class="moreInfo">'.NL;
            $this->_showWarnings($R, $data, $hasUnderscoreIssue);
            $this->_showTaxonomy($R, $data, $rel);
            $R->doc .= '</div>'.NL;
        }

        $this->_showAuthorInfo($R, $data, $rel);

        $R->doc .= '</div>'; // pluginrepo_entry
    }

    function _showMainInfo(&$R, $data, $extensionType) {
        $R->doc .= '<div class="mainInfo">'.NL;

        /* plugin/template name omitted because each page usually already has an h1 with the same information
        $R->doc .= '<h4>'.noNS($id).' '.$extensionType.'</h4>';
        */

        // icon and description
        $extensionIcon = '<a class="media" href="'.wl($extensionType.'s').'">'.
            '<img alt="'.$extensionType.'" class="medialeft" src="'.
            DOKU_BASE.'lib/plugins/pluginrepo/images/dwplugin.png" width="60" height="60" /></a> ';
        $R->doc .= '<p class="description">'.$extensionIcon.hsc($data['description']).'</p>'.NL;

        // screenshot
        if ($data['screenshot_img']) {
            $val = $data['screenshot_img'];
            $R->doc .= '<a href="'.ml($val).'" class="media screenshot" rel="lightbox">';
            $R->doc .= '<img src="'.ml($val,"w=220").'" alt="" width="220" /></a>'.NL;
        }

        $R->doc .= '</div>';
    }

    function _showMetaInfo(&$R, $data, $type, $rel) {
        global $ID;
        $target = getNS($ID);
        if($target == 'plugin') {
            $target .= 's';
        }

        $R->doc .= '<div class="metaInfo"><dl>'.NL;

        // last updated
        if(preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])){
            $R->doc .= '<dt>'.$this->getLang('last_updated_on').'</dt>'.NL;
            $R->doc .= '<dd>'.$data['lastupdate'].'</dd>'.NL;
        }

        // plugin type
        if($type && $type != 32){
            $R->doc .= '<dt>'.$this->getLang('provides').'</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listtype($type,$target).'</dd>'.NL;
        }

        // repository
        if($data['sourcerepo']) {
            $R->doc .= '<dt>'.$this->getLang('sourcerepo').'</dt>'.NL;
            $R->doc .= '<dd><a class="urlextern" href="'.hsc($data['sourcerepo']).'">'.$this->getLang('source').'</a></dd>'.NL;
        }

        // conflicts
        if ($rel['conflicts']) {
            $data['conflicts'] .= ','.join(',',$rel['conflicts']);
        }
        if($data['conflicts']){
            $R->doc .= '<dt>'.$this->getLang('conflicts_with').'</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listplugins($data['conflicts'],$R).'</dd>'.NL;
        }

        // dependencies
        if($data['depends']){
            $R->doc .= '<dt>'.$this->getLang('requires').'</dt>'.NL;
            $R->doc .= '<dd>'.$this->hlp->listplugins($data['depends'],$R).'</dd>'.NL;
        }

        $R->doc .= '</dl></div>'.NL;
    }

    function _showCompatibility(&$R, $data) {
        $R->doc .= '<div class="compatibility">';
        $R->doc .= '<p class="label">'.$this->hlp->renderCompatibilityHelp().'</p>'.NL;

        // no compatibility data
        if (!$data['compatible']) {
            $R->doc .= '<p class="nothing">';
            $R->doc .= $this->getLang('no_compatibility');
            $R->doc .= '</p>'.NL;
        // compatibility data given
        } else {
            // get recent compatible releases
            $compatibility = $this->hlp->cleanCompat($data['compatible']);
            $cols = 0;
            $norecentcompat = true;
            $compatrow = '';
            foreach ($this->hlp->dokuReleases as $release) {
                if (++$cols > 4) break;
                $value = $this->getLang('compatible_unknown');
                $compaticon = "";
                if (array_key_exists($release['date'], $compatibility)) {
                    $value = $this->getLang('compatible_yes');
                    $compaticon = "yes";
                    if ($compatibility[$release['date']]['implicit']) {
                        $value = $this->getLang('compatible_probably');
                        $compaticon = "probably";
                    }
                    $norecentcompat = false;
                }
                $compatrow .= '<li class="'.$compaticon.'">'.$release['date'].' '.$release['label'];
                $compatrow .= '&nbsp;<strong><span>'.$value.'</span></strong></li>'.NL;
            }

            // compatible to devel
            if (strpos($data['compatible'],'devel') !== false) {
                $R->doc .= '<p>';
                $R->internallink('devel:develonly',$this->getLang('develonly'));
                $R->doc .= '</p>'.NL;
            // compatible to older releases
            } elseif ($norecentcompat) {
                $R->doc .= '<p>';
                $R->doc .= $data['compatible'];
                $R->doc .= '</p>'.NL;
            // compatible to recent releases
            } else {
                $R->doc .= '<div class="versions"><ul>'.NL.$compatrow.'</ul></div>'.NL;
            }
        }

        $R->doc .= '</div>'.NL;
    }

    function _showActionLinks(&$R, $data) {
        if ($data['downloadurl'] || $data['bugtracker'] || $data['donationurl']) {
            $R->doc .= '<ul class="actions">'.NL;
            if($data['downloadurl'])
                $R->doc .= '<li><a class="download" href="'.hsc($data['downloadurl']).'">'.
                           $this->getLang('downloadurl').'</a></li>'.NL;
            if($data['bugtracker'])
                $R->doc .= '<li><a class="bugs" href="'.hsc($data['bugtracker']).'">'.
                           $this->getLang('bugtracker').'</a></li>'.NL;
            if($data['donationurl'])
                $R->doc .= '<li><a class="donate" href="'.hsc($data['donationurl']).'">'.
                           $this->getLang('donationurl').'</a></li>'.NL;
            $R->doc .= '</ul><div class="clearer"></div>'.NL;
        }
    }

    function _showWarnings(&$R, $data, $hasUnderscoreIssue) {
        if($data['securitywarning']){
            $R->doc .= '<div class="notify">'.NL;
            $securitylink = $R->internallink('devel:security',$this->getLang('securitylink'),NULL,true);
            $R->doc .= '<p><strong>'.sprintf($this->getLang('securitywarning'),$securitylink).'</strong> ';
            if(in_array($data['securitywarning'],$this->hlp->securitywarning)){
                $R->doc .= $this->getLang('security_'.$data['securitywarning']);
            }else{
                $R->doc .= hsc($data['securitywarning']);
            }
            $R->doc .= '</p>'.NL.'</div>'.NL;
        }

        if($data['securityissue']){
            $R->doc .= '<div class="error">'.NL;
            $R->doc .= '<p><strong>'.$this->getLang('securityissue').'</strong> ';
            $R->doc .= hsc($data['securityissue']);
            $securitylink = $R->internallink('devel:security',$this->getLang('securitylink'),NULL,true);
            $R->doc .= '</p>'.NL.'<p>'.sprintf($this->getLang('securityrecommendation'),$securitylink).'</p>'.NL;
            $R->doc .= '</div>'.NL;
        }

        if($hasUnderscoreIssue) {
            $R->doc .= '<div class="info"><p>';
            $R->doc .= $this->getLang('name_underscore');
            $R->doc .= '</p></div>'.NL;
        }
    }

    function _showTaxonomy(&$R, $data, $rel) {
        global $ID;
        $target = getNS($ID);
        if($target == 'plugin') {
            $target .= 's';
        }

        // similar extensions
        if ($rel['similar']) {
            $data['similar'] .= ','.join(',',$rel['similar']);
        }
        if($data['similar']){
            $R->doc .= '<p class="similar">'.$this->getLang('similar_to').' ';
            $R->doc .= $this->hlp->listplugins($data['similar'],$R).'</p>'.NL;
        }
        // tags
        if($data['tags']){
            $R->doc .= '<p class="tags">'.$this->getLang('tagged_with').' ';
            $R->doc .= $this->hlp->listtags($data['tags'],$target).'</p>'.NL;
        }
        // Needed for
        if($rel['needed']){
            $R->doc .= '<p class="needed">'.$this->getLang('needed_for').' ';
            $R->doc .= $this->hlp->listplugins($rel['needed'],$R).'</p>'.NL;
        }
    }

    function _showAuthorInfo(&$R, $data, $rel) {
        $R->doc .= '<div class="authorInfo">'.NL;

        // author
        $R->doc .= '<strong>'.ucfirst($this->getLang('by')).' ';
        if ($data['email']) {
            $R->emaillink($data['email'],$data['author']);
        } else {
            $R->doc .= $data['author'];
        }
        $R->doc .= '</strong>'.NL;

        // other extensions by the same author (10 max)
        if (count($rel['sameauthor']) > 0) {
            $itr = 0;
            $R->doc .= '<ul>'.NL;
            while ($itr < count($rel['sameauthor']) && $itr < 10) {
                $R->doc .= '<li>'.$this->hlp->pluginlink($R,$rel['sameauthor'][$itr++]).'</li>'.NL;
            }
            $R->doc .= '</ul>'.NL;
        }

        $R->doc .= '</div>'.NL;
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

        $stmt = $db->prepare('INSERT INTO plugins
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
                                :screenshot, :tags, :type)
                              ON DUPLICATE KEY UPDATE
                                name            = :name,
                                description     = :description,
                                author          = :author,
                                email           = LOWER(:email),
                                compatible      = :compatible,
                                bestcompatible  = :bestcompatible,
                                lastupdate      = :lastupdate,
                                securityissue   = :securityissue,
                                securitywarning = :securitywarning,
                                downloadurl     = :downloadurl,
                                bugtracker      = :bugtracker,
                                sourcerepo      = :sourcerepo,
                                donationurl     = :donationurl,
                                screenshot      = :screenshot,
                                tags            = :tags,
                                type            = :type

                            ');
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
