<?php

/**
 * DokuWiki popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */
class helper_plugin_pluginrepo_popularity extends DokuWiki_Plugin {
    /** @var helper_plugin_pluginrepo_repository $hlp */
    protected $hlp;

    public function helper_plugin_pluginrepo_popularity() {
        $this->hlp = $this->loadHelper('pluginrepo_repository');
    }

    /**
     * Counts the values of the requested key, in given time window
     *
     * @param string $key
     * @param string $orderby 'cnt' or 'val'
     * @param string $startdate YYYY-MM-DD only submits after this date
     * @param string $enddate YYYY-MM-DD only submits until this date
     * @param int $daysago if $startdate not set, retrieve the submits for this number of days
     * @return array
     */
    public function getCounts($key, $orderby = 'cnt', $startdate = '', $enddate = '', $daysago = 0) {
        $db = $this->hlp->_getPluginsDB();
        if(!$db) return array();

        $keys = array();

        switch($key) {
            case 'page_size':
                $select = "CONCAT(ROUND(pop.value/(1024)),'KB')";
                break;
            case 'media_size':
                $select = "ROUND(pop.value/(1024*1024))";
                break;
            case 'webserver':
                $select = "SUBSTRING_INDEX(pop.value,'/',1)";
                break;
            case 'php_version':
                $select = "CONCAT('PHP ',SUBSTRING(pop.value,1,3))";
                break;
            default:
                $select = "REPLACE( REPLACE( pop.value, '\\\\\"', '\"') , '&quot;', '\"')";
        }
        $keys[':key'] = $key;

        // add time restrictions
        if($startdate OR $daysago) {
            $join = "LEFT JOIN popularity now ON pop.uid=now.uid";
            $where = " AND now.key = 'now'";
            if($startdate) {
                $where .= " AND now.value > UNIX_TIMESTAMP( :start )";
                $keys[':start'] = $startdate;
                if($enddate) {
                    $where .= "  AND now.value < UNIX_TIMESTAMP( :end )";
                    $keys[':end'] = $enddate;
                }
            } else {
                $ago = time() - (int)$daysago * 24 * 60 * 60;
                $where .= " AND now.value > $ago";
            }
        } else {
            $join = '';
            $where = '';
        }

        $orderbyfields = array('val', 'cnt');
        if(!in_array($orderby, $orderbyfields)) {
            $orderby = 'cnt';
        }

        $stmt = $db->prepare(
                   "SELECT $select AS val, COUNT(*) AS cnt
                      FROM popularity pop
                     $join
                     WHERE pop.key = :key
                           $where
                  GROUP BY val
                  ORDER BY $orderby DESC"
        );

        $stmt->execute($keys);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }

    /**
     * Retrieves number of unique wikis which submits statistics
     *
     * @return int
     */
    public function getNumberOfSubmittingWikis() {
        $db = $this->hlp->_getPluginsDB();
        if(!$db) return 0;

        $stmt = $db->prepare('SELECT COUNT(DISTINCT uid) AS cnt FROM popularity');
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows[0]['cnt'];
    }
}