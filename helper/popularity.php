<?php

use dokuwiki\Extension\Plugin;

/**
 * DokuWiki popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */
class helper_plugin_pluginrepo_popularity extends Plugin
{
    /** @var helper_plugin_pluginrepo_repository $hlp */
    protected $hlp;

    public function __construct()
    {
        $this->hlp = $this->loadHelper('pluginrepo_repository');
    }

    /**
     * Counts the values of the requested key, in given time window
     *
     * @param string $key
     * @param string $orderby   'cnt' or 'val'
     * @param string $startdate YYYY-MM-DD only submits after this date
     * @param string $enddate   YYYY-MM-DD only submits until this date
     * @param int    $daysago   if $startdate not set, retrieve the submits for this number of days
     * @return array
     */
    public function getCounts($key, $orderby = 'cnt', $startdate = '', $enddate = '', $daysago = 0)
    {
        $db = $this->hlp->getPluginsDB();
        if (!$db) {
            return [];
        }

        $replacements = [];

        switch ($key) {
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
        $replacements[':key'] = $key;

        // add time restrictions
        $where = $this->buildWhere($startdate, $enddate, $daysago, $replacements);

        $orderbyfields = ['val', 'cnt'];
        if (!in_array($orderby, $orderbyfields)) {
            $orderby = 'cnt';
        }

        $stmt = $db->prepare(
            "SELECT $select AS val, COUNT(*) AS cnt
                      FROM popularity pop
                     WHERE pop.key = :key
                           $where
                  GROUP BY val
                  ORDER BY $orderby DESC"
        );

        $stmt->execute($replacements);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves number of unique wikis which submits statistics
     *
     * @param string $startdate YYYY-MM-DD only submits after this date
     * @param string $enddate   YYYY-MM-DD only submits until this date
     * @param int    $daysago   if $startdate not set, retrieve the submits for this number of days
     * @return int
     */
    public function getNumberOfSubmittingWikis($startdate = '', $enddate = '', $daysago = 0)
    {
        $db = $this->hlp->getPluginsDB();
        if (!$db) {
            return 0;
        }

        $replacements = [];
        $where = $this->buildWhere($startdate, $enddate, $daysago, $replacements);

        $stmt = $db->prepare(
            "SELECT COUNT(DISTINCT pop.uid) AS cnt
                      FROM popularity pop
                     WHERE 1=1
                           $where"
        );

        $stmt->execute($replacements);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows[0]['cnt'];
    }

    /**
     * Builds the join and where statements
     *
     * @param string $startdate     YYYY--MM-DD
     * @param string $enddate       YYYY--MM-DD
     * @param int    $daysago       show data back to given days ago
     * @param array  $replacements  (reference) can be extended with additional keys
     * @return string
     */
    public function buildWhere($startdate, $enddate, $daysago, &$replacements)
    {
        $where = '';
        if ($startdate || $daysago) {
            if ($startdate) {
                $where .= " AND dt > UNIX_TIMESTAMP( :start )";
                $replacements[':start'] = $startdate;
                if ($enddate) {
                    $where .= "  AND dt < UNIX_TIMESTAMP( :end )";
                    $replacements[':end'] = $enddate;
                }
            } else {
                $ago = time() - (int) $daysago * 24 * 60 * 60;
                $where .= " AND dt > $ago";
            }
        }
        return $where;
    }
}
