<?php

/**
 * Class helper_plugin_pluginrepo_version
 */
class helper_plugin_pluginrepo_version extends DokuWiki_Plugin {

    /** @var  DokuHTTPClient */
    protected $http;
    /** @var  JSON */
    protected $json;

    /**
     * Constructor, initializes helper classes
     */
    public function __construct() {
        $this->http = new DokuHTTPClient();
        $this->json = new JSON(JSON_LOOSE_TYPE);
    }

    public function execute() {

        if(!$this->getConf('github_user')) return;
        if(!$this->getConf('github_key')) return;

        // prepare the page
        $out = io_readFile(__DIR__.'/../lang/en/version.preamble');
        $out .= "\n";
        $out .= "\n";
        $out .= '^';
        $out .= sprintf(' %-110s ^', 'Extension');
        $out .= sprintf(' %-15s ^', 'Plugin Page');
        $out .= sprintf(' %-15s ^', 'info.txt');
        $out .= sprintf(' %-15s ^', 'Last Commit');
        $out .= sprintf(' %-15s ^', 'base');
        $out .= "\n";

        /** @var helper_plugin_pluginrepo_repository $repo */
        $repo = plugin_load('helper', 'pluginrepo_repository');

        $extensions = $repo->getPlugins(array('showall' => true, 'includetemplates' => true));
        foreach($extensions as $extension) {
            $github = $this->getGitHubInfo($extension);
            if($github) {
                $out .= $this->getDiscrepancies($extension, $github);
            }
        }

        saveWikiText('devel:badextensions', $out, 'auto update');
    }

    /**
     * @param $plugindata
     * @param $githubdata
     * @return string
     */
    protected function getDiscrepancies($plugindata, $githubdata) {
        $date1error = '';
        $date2error = '';
        $nameerror  = '';
        if($plugindata['lastupdate'] != $githubdata['date']) $date1error = ' :!:';
        if($plugindata['lastupdate'] != $githubdata['gitpush']) $date2error = ' :!:';
        if($plugindata['simplename'] != $githubdata['base']) $nameerror = ' :!:';

        if(!$date1error && !$date2error && !$nameerror) return '';

        if($plugindata['type'] == 32) {
            $link = $plugindata['plugin'];
        } else {
            $link = 'plugin:'.$plugindata['plugin'];
        }

        $out = '|';
        $out .= sprintf(' %-110s |', '[['.$link.'|'.$plugindata['name'].']] by '.$plugindata['author']);
        $out .= sprintf(' %-15s |', $plugindata['lastupdate']);
        $out .= sprintf(' %-15s |', $githubdata['date'].$date1error);
        $out .= sprintf(' %-15s |', $githubdata['gitpush'].$date2error);
        $out .= sprintf(' %-15s |', $githubdata['base'].$nameerror);
        $out .= "\n";

        return $out;
    }

    /**
     * This fetches the available info about the given extension from the github repository
     *
     * @param array $plugindata all the data about the plugin from plugin repo
     * @return bool|array the gathered info or fals if not available
     */
    protected function getGitHubInfo($plugindata) {
        if(empty($plugindata['sourcerepo'])) return false;
        if(!preg_match('/github\.com\/([^\/]+)\/([^\/]+)/i', $plugindata['sourcerepo'], $m)) return false;
        $user = $m[1];
        $repo = $m[2];

        $infotxt = 'plugin.info.txt';
        if($plugindata['type'] == 32) $infotxt = 'template.info.txt';

        /* get plugin info file data */
        $this->http->user = '';
        $this->http->pass = '';
        $info             = $this->http->get('https://raw.githubusercontent.com/'.$user.'/'.$repo.'/master/'.$infotxt);
        if(!$info) return false;
        $info = linesToHash(explode("\n", $info));

        /* add last push date to info */
        $this->http->user = $this->getConf('github_user');
        $this->http->pass = $this->getConf('github_key');
        $repoinfo         = $this->http->get("https://api.github.com/repos/$user/$repo");
        if($repoinfo) {
            $repoinfo = $this->json->decode($repoinfo);
            if(isset($repoinfo['pushed_at'])) {
                $info['gitpush'] = substr($repoinfo['pushed_at'], 0, 10);
            }
        }

        return $info;
    }
}