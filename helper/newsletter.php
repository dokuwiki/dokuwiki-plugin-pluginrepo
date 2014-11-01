<?php
/**
 * DokuWiki developer subscriptions
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
class helper_plugin_pluginrepo_newsletter extends DokuWiki_Plugin {

    protected $apikey = '';
    protected $listid = '';

    /**
     * Subscribe the new developers
     */
    public function execute() {
        $this->apikey = trim($this->getConf('mailchimp_apikey'));
        $this->listid = trim($this->getConf('mailchimp_listid'));
        if(!$this->apikey) return;
        if(!$this->listid) return;

        $batch = $this->buildBatch();
        if(!$batch) return;
        $this->batchSubscribe($batch);
    }

    /**
     * Gets all the plugin and template authors and builds a batch from it
     *
     * @return array
     */
    protected function buildBatch() {
        /** @var helper_plugin_pluginrepo_repository $hlp */
        $hlp = plugin_load('helper','pluginrepo_repository');
        $db  = $hlp->_getPluginsDB();
        if (!$db) return array();

        $batch = array();
        $sql = "SELECT email, author
                  FROM plugins
              GROUP BY email;";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $batch[] = array(
                'email' => array('email' => $row['email']),
                'email_type' => 'html',
                'merge_vars' => array('NAME' => $row['author'])
            );
        }

        return $batch;
    }

    /**
     * Calls the Mail Chimp API to subscribe the given batch of users
     *
     * Mailchimp will ignore all existing or previously unsubscribed users for us
     *
     * @param array $batch A struct as described at the Mailchimp documentation
     * @link http://apidocs.mailchimp.com/api/2.0/lists/batch-subscribe.php
     */
    protected function batchSubscribe($batch) {
        $region   = substr($this->apikey, -3);
        $endpoint = "https://$region.api.mailchimp.com/2.0/lists/batch-subscribe.json";

        $http     = new DokuHTTPClient();
        $http->post(
            $endpoint,
            array(
                 'apikey'            => $this->apikey,
                 'id'                => $this->listid,
                 'batch'             => $batch,
                 'double_optin'      => false,
                 'update_existing'   => false,
                 'replace_interests' => false
            )
        );
        // we don't really care for the results
    }
}

