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
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *plugin *-+\n.*?\n----+',$mode,'plugin_pluginrepo_entry');
    }

    /**
     * Handle the match - parse the data
     */
    function handle($match, $state, $pos, &$handler){
        return $this->hlp->parseData($match);
    }

    /**
     * Create output or save the data
     */
    function render($format, &$renderer, $data) {
        global $ID;
        switch ($format){
            case 'xhtml':
                $this->_showData($data,$renderer);
                return true;
            case 'metadata':
                // only save if in first level namespace to ignore translated namespaces
                if(substr_count($ID,':') == 1){
                    $this->_saveData($data,noNS($ID),$renderer->meta['title']);
                }
                return true;
            case 'plugin_data_edit':
            // TODO: add edit form functionality
//                $this->_editData($data, $renderer);
                return true;
            default:
                return false;
        }
    }

    /**
     * Override getLang to be able to select language by namespace
     */
    function getLang($langcode,$id) {
        return $this->hlp->getLang($langcode,$id);
    }

    /**
     * Output the data in a table
     */
    function _showData($data,&$R){
        global $ID;
        $id = noNS($ID);
        $lang = split(":",getNS($ID),2);
        $lang =(count($lang) == 2 ? $lang[0] : 'en');

        $rel = $this->hlp->getPluginRelations($id);

        // TODO: add startSectionEdit etc from DATA plugin
        // TODO: better CSS for same author
        // TODO: different for 3 and more, limit when 50
        if ($rel['sameauthor']) {
            $R->doc .= '<div id="pluginrepo__pluginauthorpush">';
            $R->doc .= $this->getLang($lang,'by_same_author');
            $R->doc .= '<ul><li>';
            $R->doc .= $this->hlp->listplugins($rel['sameauthor'],$R,'</li><li>');
            $R->doc .= '</li></ul></div>';
        }

        $R->doc .= '<div id="pluginrepo__plugin"><div>';
        $R->doc .= '<p><strong>'.$id.' plugin</strong> by ';
        $R->emaillink($data['email'],$data['author']);
        $R->doc .= '<br />'.hsc($data['description']).'</p>';

        $R->doc .= '<p>';
        if(preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])){
            $R->doc .= '<span class="lastupd">';
            $R->doc .= $this->getLang($lang,'last_updated_on');
            $R->doc .= ' <em>'.$data['lastupdate'].'</em>.</span> ';
        }
        $type = $this->hlp->parsetype($data['type']);
        if($type){
            $R->doc .= '<span class="type">';
            $R->doc .= $this->getLang($lang,'provides');
            $R->doc .= ' <em>'.$this->hlp->listtype($type).'</em>.</span>';
        }

        $R->doc .= '<br />';
        if($data['compatible']){
            $R->doc .= '<span class="compatible">';
            $R->doc .= $this->getLang($lang,'compatible_with');
            $R->doc .= ' <em>DokuWiki '.hsc($data['compatible']).'</em>.</span>';
            
            $R->doc .= '<table class="inline">';
            $R->doc .= '<tr><th>2009-02-14</th><th>2009-12-25<br/>"Lemming"</th><th>2009-02-25<br/>"Anteater"</th></th>';
            $R->doc .= '<tr><td>y</td><td>y</td><td></td></tr>';
            $R->doc .= '</table>';
        }else{
            $R->doc .= '<span class="compatible">';
            $R->doc .= $this->getLang($lang,'no_compatibility');
            $R->doc .= '</span>';
        }
        $R->doc .= '</p></div>';

        $R->doc .= '<p>';
        if ($rel['conflicts']) {
            $data['conflicts'] .= ','.$rel['conflicts'];
        }
        if($data['conflicts']){
            $R->doc .= '<span class="conflicts">';
            $R->doc .= $this->getLang($lang,'conflicts_with');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listplugins($data['conflicts'],$R);
            $R->doc .= '</em>!</span><br />';
        }
        if($data['depends']){
            $R->doc .= '<span class="depends">';
            $R->doc .= $this->getLang($lang,'requires');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listplugins($data['depends'],$R);
            $R->doc .= '</em>.</span><br />';
        }

        if ($rel['similar']) {
            $data['similar'] .= ','.$rel['similar'];
        }
        if($data['similar']){
            $R->doc .= '<span class="similar">';
            $R->doc .= $this->getLang($lang,'similar_to');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listplugins($data['similar'],$R);
            $R->doc .= '</em>.</span><br />';
        }
        $R->doc .= '</p>';

        $R->doc .= '<p>';
        if($data['tags']){
            $R->doc .= '<span class="tags">';
            $R->doc .= $this->getLang($lang,'tagged_with');
            $R->doc .= ' <em>';
            $R->doc .= $this->hlp->listtags($data['tags']);
            $R->doc .= '</em>.</span><br />';
        }
        $R->doc .= '</p>';

// TODO: new security warning function ?
        if($data['securityissue']){
            $R->doc .= '<p class="security">';
            $R->doc .= '<b>'.$this->getLang($lang,'securityissue').'</b><br /><br />';
            $R->doc .= '<i>'.hsc($data['securityissue']).'</i><br /><br />';
            $securitylink = $R->internallink('devel:security',$this->getLang($lang,'securitylink'),NULL,true);
            $R->doc .= sprintf($this->getLang($lang,'securityrecommendation'),$securitylink);
            $R->doc .= '.</p>';
        }
        $R->doc .= '</div>';

        // add tabs
        $R->doc .= '<ul id="pluginrepo__foldout">';
        if($data['downloadurl']) $R->doc .= '<li><a class="download" href="'.hsc($data['downloadurl']).'">'.$this->getLang($lang,'downloadurl').'</a></li>';
        if($data['bugtracker'])  $R->doc .= '<li><a class="bugs" href="'.hsc($data['bugtracker']).'">'.$this->getLang($lang,'bugtracker').'</a></li>';
        if($data['sourcerepo'])  $R->doc .= '<li><a class="repo" href="'.hsc($data['sourcerepo']).'">'.$this->getLang($lang,'sourcerepo').'</a></li>';
        if($data['donationurl']) $R->doc .= '<li><a class="donate" href="'.hsc($data['donationurl']).'">'.$this->getLang($lang,'donationurl').'</a></li>';
        $R->doc .= '</ul>';
    }

    /**
     * Save date to the database
     */
    function _saveData($data,$id,$name){
        $db = $this->hlp->_getPluginsDB();
        if (!$db) return;

        if(!$name) $name = $id;
        if(!preg_match('/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/',$data['lastupdate'])){
            $data['lastupdate'] = 'NULL';
        }else{
            $data['lastupdate'] = "'".$data['lastupdate']."'";
        }

        $type = $this->hlp->parsetype($data['type']);

        $sql = "REPLACE INTO plugins
                    SET plugin      = '$id',
                        name        = '$name',
                        description = '".$data['description']."',
                        author      = '".$data['author']."',
                        email       = LOWER('".$data['email']."'),
                        compatible  = '".$data['compatible']."',
                        lastupdate  = ".$data['lastupdate'].",
                        securityissue = '".$data['securityissue']."',
                        downloadurl = '".$data['downloadurl']."',
                        bugtracker  = '".$data['bugtracker']."',
                        sourcerepo  = '".$data['sourcerepo']."',
                        donationurl = '".$data['donationurl']."',
                        type        = $type";
    // TODO: REPLACE INTO SET doesn't work in sqlite ?
        $sql = "REPLACE INTO plugins
                    (plugin, name, description, author, email, compatible, lastupdate, securityissue, downloadurl, bugtracker, sourcerepo, donationurl, type)
                VALUES
                    ('$id', '$name', '".$data['description']."', '".$data['author']."', LOWER('".$data['email']."'), 
					 '".$data['compatible']."', ".$data['lastupdate'].", '".$data['securityissue']."', '".$data['downloadurl']."', '".$data['bugtracker']."', '".$data['sourcerepo']."', '".$data['donationurl']."', $type)";
        $db->exec($sql);
        // $stmt = $db->prepare('REPLACE INTO plugins 
                               // (plugin, name, description, 
                                // author, email, 
                                // compatible, lastupdate, securityissue, 
                                // downloadurl, bugtracker, sourcerepo, donationurl, 
                                // type)
                              // VALUES
                               // (:plugin, :name, :description, 
                                // :author, LOWER(:email), 
                                // :compatible, :lastupdate, :securityissue, 
                                // :downloadurl, :bugtracker, :sourcerepo, :donationurl, 
                                // :type) ');
        // $stmt->execute(array(':plugin' => $id, 
                             // ':name' => $name, 
                             // ':description' => $data['description'], 
                             // ':author' => $data['author'], 
                             // ':email' => $data['email'], 
                             // ':compatible' => $data['compatible'], 
                             // ':lastupdate' => $data['lastupdate'], 
                             // ':securityissue' => $data['securityissue'], 
                             // ':downloadurl' => $data['downloadurl'], 
                             // ':bugtracker' => $data['bugtracker'], 
                             // ':sourcerepo' => $data['sourcerepo'], 
                             // ':donationurl' => $data['donationurl'], 
                             // ':type' => $type));

        $tags = $this->hlp->parsetags($data['tags']);
        $stmt = $db->prepare('DELETE FROM plugin_tags WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($tags as $tag){
            $stmt = $db->prepare('INSERT OR IGNORE INTO plugin_tags (plugin, tag) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$tag));
        }

        $deps = explode(',',$data['depends']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $stmt = $db->prepare('DELETE FROM plugin_depends WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($deps as $dep){
            $stmt = $db->prepare('INSERT OR IGNORE INTO plugin_depends (plugin, other) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$dep));
        }

        $deps = explode(',',$data['conflicts']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $stmt = $db->prepare('DELETE FROM plugin_conflicts WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($deps as $dep){
            $stmt = $db->prepare('INSERT OR IGNORE INTO plugin_conflicts (plugin, other) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$dep));
        }

        $deps = explode(',',$data['similar']);
        $deps = array_map('trim',$deps);
        $deps = array_filter($deps);
        $stmt = $db->prepare('DELETE FROM plugin_similar WHERE plugin = ?');
        $stmt->execute(array($id));
        foreach($deps as $dep){
            $stmt = $db->prepare('INSERT OR IGNORE INTO plugin_similar (plugin, other) VALUES (?,LOWER(?))');
            $stmt->execute(array($id,$dep));
        }

        // TODO: remove debug
        $stmt = $db->prepare('DELETE FROM popularity WHERE popularity.value = ?');
        $stmt->execute(array($id));
        $users = rand(0,20);
        for ($i = 0; $i < $users; $i++) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO popularity (uid, key, value) VALUES (?,?,?)');
            $stmt->execute(array("U".$i,'plugin',$id));
        }
    }
}
