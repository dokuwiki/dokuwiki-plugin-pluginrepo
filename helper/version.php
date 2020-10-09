<?php

use dokuwiki\HTTP\HTTPClient;

/**
 * Class helper_plugin_pluginrepo_version
 */
class helper_plugin_pluginrepo_version extends DokuWiki_Plugin {

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
        if($plugindata['lastupdate'] < $githubdata['gitpush']) $date2error = ' :!:';
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
     * @return bool|array the gathered info or false if not available
     */
    protected function getGitHubInfo($plugindata) {
        if(empty($plugindata['sourcerepo'])) return false;
        if(!preg_match('/github\.com\/([^\/]+)\/([^\/]+)/i', $plugindata['sourcerepo'], $m)) return false;
        $user = $m[1];
        $repo = $m[2];

        // prepare an authenticated HTTP client
        $http                    = new HTTPClient();
        $http->headers['Accept'] = 'application/vnd.github.v3+json';
        $http->user              = $this->getConf('github_user');
        $http->pass              = $this->getConf('github_key');

        // get the current version in the *info.txt file
        $infotxt = 'plugin.info.txt';
        if($plugindata['type'] == 32) $infotxt = 'template.info.txt';

        $url      = 'https://api.github.com/repos/'.$user.'/'.$repo.'/contents/'.$infotxt;
        $response = $http->get($url);
        if(!$response) return false;

        $response = json_decode($response, true);
        $infotxt  = base64_decode($response['content']);
        $info     = linesToHash(explode("\n", $infotxt));
        if(empty($info['date'])) return false;

        // get the latest significant commit
        $url     = 'https://api.github.com/repos/'.$user.'/'.$repo.'/commits?per_page=100';
        $commits = $http->get($url);
        if(!$commits) return false;
        $commits = json_decode($commits, true);

        $comversion = substr($commits[0]['commit']['author']['date'], 0, 10); // default to newest
        foreach($commits as $commit) {
            if(preg_match('/^Merge/i', $commit['commit']['message'])) continue; // skip merges
            if(preg_match('/^Version upped$/i', $commit['commit']['message'])) continue; // skip version tool updates
            if($commit['commit']['committer']['email'] == 'translate@dokuwiki.org') continue; //skip translations

            $comversion = substr($commit['commit']['author']['date'], 0, 10);
            break;
        }
        $info['gitpush'] = $comversion;

        return $info;
    }
}