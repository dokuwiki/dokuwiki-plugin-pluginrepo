<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Utf8\PhpString;
use dokuwiki\Utf8\Sort;

/**
 * DokuWiki plugin/template/popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <hakan.sandell@home.se>
 */
class helper_plugin_pluginrepo_repository extends Plugin
{
    public array $dokuReleases = []; // array of DokuWiki releases (name & date)

    public array $types = [
        1   => 'Syntax',
        2   => 'Admin',
        4   => 'Action',
        8   => 'Render',
        16  => 'Helper',
        32  => 'Template',
        64  => 'Remote',
        128 => 'Auth',
        256 => 'CLI',
        512 => 'CSS/JS-only'
    ];

    public string $obsoleteTag = '!obsolete';
    public array $bundled;
    public array $securitywarning = ['informationleak', 'allowsscript', 'requirespatch', 'partlyhidden'];

    /**
     * helper_plugin_pluginrepo_repository constructor.
     */
    public function __construct()
    {
        $this->bundled = explode(',', $this->getConf('bundled'));
        $this->bundled = array_map('trim', $this->bundled);
        $this->bundled = array_filter($this->bundled);
        $this->bundled = array_unique($this->bundled);
    }

    /**
     * Parse syntax data block, return keyed array of values
     *
     *  You may use the # character to add comments to the block.
     *  Those will be ignored and will neither be displayed nor saved.
     *  If you need to enter # as data, escape it with a backslash (\#).
     *  If you need a backslash, escape it as well (\\)
     *
     * @param string $match data block
     * @param array $data array with entries with initial values
     * @return array
     */
    public function parseData($match, $data)
    {
        // get lines
        $lines = explode("\n", $match);
        array_pop($lines);
        array_shift($lines);

        // parse info
        foreach ($lines as $line) {
            // ignore comments and bullet syntax
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = preg_replace('/^  \* /', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            [$key, $value] = preg_split('/\s*:\s*/', $line, 2);
            $key = strtolower($key);
            $value = $this->convertToType($key, trim($value));
            if ($data[$key] === '') {
                $data[$key] .= ' ' . $value;
            } else {
                $data[$key] = $value;
            }
        }
        // sqlite plugin compability (formerly used for templates)
        if (isset($data['lastupdate_dt'])) {
            $data['lastupdate'] = $data['lastupdate_dt'];
        }
        if (isset($data['template_tags'])) {
            $data['tags'] = $data['template_tags'];
        }
        if (isset($data['author_mail'])) {
            [$mail, $name] = preg_split('/\s+/', $data['author_mail'], 2);
            $data['author'] = $name;
            $data['email'] = $mail;
        }
        foreach ($data as $key => $value) {
            $data[$key] = $this->truncateString($key, $value);
        }
        return $data;
    }

    /**
     * Converts some entries to boolean
     *
     * @param string $key name of syntax entry
     * @param string $value value
     * @return string|int|bool converted value
     */
    public function convertToType($key, $value)
    {
        $hasYesValue = ['showall', 'includetemplates', 'showcompatible', 'showscreenshot'];
        $hasNoValue = ['random'];
        $isInteger = ['entries', 'plugintype', 'cloudmin'];
        if (in_array($key, $hasYesValue)) {
            $value = $value == 'yes';
        }
        if (in_array($key, $hasNoValue)) {
            $value = $value == 'no';
        }
        if (in_array($key, $isInteger)) {
            if (is_numeric($value)) {
                $value = (int) $value;
            }
        }

        return $value;
    }

    /**
     * Shorten entries that are stored as 255 varchar string in MySQL.
     *
     * @param string $key
     * @param mixed|string $value
     * @return mixed|string
     */
    public function truncateString($key, $value)
    {
        $is255chars = [
            'name', 'description', 'author', 'email', 'compatible', 'securityissue', 'securitywarning',
            'updatemessage', 'downloadurl', 'bugtracker', 'sourcerepo', 'donationurl', 'screenshot', 'tags'
        ];

//        if (in_array($key, $is50chars)) {
//            $value = PhpString::substr($value, 0, 50);
//        }
        if (in_array($key, $is255chars)) {
            $value = PhpString::substr($value, 0, 255); //varchar(255) in MySQL>=5 count multibytes chars
        }

        return $value;
    }

    /**
     * Rewrite plugin
     *
     * @param array $data (reference) data from entry::handle
     */
    public function harmonizeExtensionIDs(&$data)
    {
        foreach (['similar', 'conflicts', 'depends'] as $key) {
            $refs = explode(',', $data[$key]);
            $refs = array_map('trim', $refs);
            $refs = array_filter($refs);

            $updatedrefs = [];
            foreach ($refs as $ref) {
                $ns = curNS($ref);
                if ($ns === false) {
                    $ns = '';
                }
                $id = noNS($ref);
                if ($ns == 'template' || $data['type'] == 'template' && $ns === '') {
                    $ns = 'template:';
                } elseif ($ns == 'plugin' || $ns === '') {
                    $ns = '';
                } else {
                    $ns .= ':';
                }
                $updatedrefs[] = $ns . $id;
            }
            $data[$key] = implode(',', $updatedrefs);
        }
    }

    /**
     * Create database connection and return PDO object
     * the config option 'db_name' must contain the
     * DataSourceName, which consists of the PDO driver name,
     * followed by a colon, followed by the PDO driver-specific connection syntax
     * see http://se2.php.net/manual/en/pdo.construct.php
     *
     * Example: 'mysql:dbname=testdb;host=127.0.0.1'
     *      or  'sqlite2:C:\DokuWikiStickNew\dokuwiki\repo.sqlite'
     */
    public function getPluginsDB()
    {
        global $conf;
        /** @var PDO $db */
        try {
            $db = new PDO($this->getConf('db_name'), $this->getConf('db_user'), $this->getConf('db_pass'));
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
                // Running on mysql; do something mysql specific here
            }
        } catch (PDOException $e) {
            msg("Repository plugin: failed to connect to database (" . $e->getMessage() . ")", -1);
            return null;
        }

        // trigger creation of tables if db empty
        try {
            $stmt = $db->prepare('SELECT 1 FROM plugin_depends LIMIT 1');
            $stmt->execute();
        } catch (PDOException $e) {
            $this->initPluginDB($db);
        }

        if ($conf['allowdebug']) {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        } else {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        }
        return $db;
    }

    /**
     * Return array of plugins with some metadata
     * Note: used by repository.php (e.g. for translation tool) and repo table
     *
     * @param array $filter with entries used
     * <ul>
     *   <li>'plugins'    (array) returns only data of named plugins</li>
     *   <li>'plugintype' (integer) filter by type, binary-code decimal so you can combine types</li>
     *   <li>'plugintag'  (string) filter by one tag</li>
     *   <li>'pluginsort' (string) sort by some specific columns (also shortcuts available)</li>
     *   <li>'showall'    (bool) default/unset is false and obsolete plugins and security issues are not returned</li>
     *   <li>'includetemplates' (bool) default/unset is false and template data will not be returned</li>
     * </ul>
     * @return array data per plugin
     */
    public function getPlugins($filter = null)
    {
        $db = $this->getPluginsDB();
        if (!$db) {
            return [];
        }

        // return named plugins OR with certain tag/type
        $requestedplugins = $filter['plugins'];
        $type    = 0;
        $tag     = '';
        $where_requested = '';
        $requestedINvalues = [];
        if ($requestedplugins) {
            if (!is_array($requestedplugins)) {
                $requestedplugins = [$requestedplugins];
            }
            [$requestedINsql, $requestedINvalues] = $this->prepareINstmt('requested', $requestedplugins);

            $where_requested = " AND A.plugin " . $requestedINsql;
        } else {
            $type = (int) $filter['plugintype'];
            $tag  = strtolower(trim($filter['plugintag']));
        }

        if ($filter['showall']) {
            $where_filtered = "1";
            $values = [];
        } else {
            [$bundledINsql, $bundledINvalues] = $this->prepareINstmt('bundled', $this->bundled);

            $where_filtered = "'" . $this->obsoleteTag . "' NOT IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)"
                . " AND A.securityissue = ''"
                . " AND (A.downloadurl <> '' OR A.plugin " . $bundledINsql . ")";
            $values = $bundledINvalues;
        }
        if (!$filter['includetemplates']) {
            $where_filtered .= " AND A.type <> 32"; // templates are only type=32, has no other type.
        }

        $sort = strtolower(trim($filter['pluginsort']));
        $sortsql = $this->getPluginsSortSql($sort);

        $alltypes = 0;
        foreach (array_keys($this->types) as $t) {
            $alltypes += $t;
        }

        if ($tag) {
            if ($type < 1 || $type > $alltypes) {
                $type = $alltypes; //all types
            }
            $sql = "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                           AND :plugin_tag IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                           AND :plugin_tag IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)
                                $sortsql";
            $values = array_merge(
                [':plugin_tag' => $tag, ':plugin_type' => $type],
                $values
            );
        } elseif ($type > 0 && $type <= $alltypes) {
            $sql = "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                 $sortsql";
            $values = array_merge(
                [':plugin_type' => $type],
                $values
            );
        } else {
            $sql = "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $where_filtered
                                    $where_requested
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $where_filtered
                                    $where_requested
                                 $sortsql";

            $values = array_merge(
                $requestedINvalues,
                $values
            );
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prepares IN statement with placeholders and array with the placeholder values
     *
     * @param string $paramlabel
     * @param array $values
     * @return array with
     *      sql as string
     *      params as associated array
     */
    protected function prepareINstmt($paramlabel, $values)
    {
        $count = 0;
        $params  = [];
        foreach ($values as $value) {
            $params[':' . $paramlabel . $count++] = $value;
        }

        $sql = 'IN (' . implode(',', array_keys($params)) . ')';
        return [$sql, $params];
    }

    /**
     * Returns all plugins and templates from the database
     *
     * @return array extensions same as above, but without 'simplename' column
     */
    public function getAllExtensions()
    {
        $db = $this->getPluginsDB();
        if (!$db) {
            return [];
        }

        $sql = "SELECT A.*
                  FROM plugins A
                  ORDER BY A.plugin";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gives all available info on plugins. Used for API
     *
     * tags, similar, depends, conflicts have newline separated lists
     *
     * @param array  $names    names of wanted extensions (use template: prefix)
     * @param array  $emailids md5s of emails of wanted extension authors
     * @param int    $type     ANDed types you want, 0 for all
     * @param array  $tags     show only extensions with these tags
     * @param string $order    order by this column
     * @param int    $limit    number of items
     * @param string $fulltext search term for full text search
     *
     * @return array
     * @throws Exception
     */
    public function getFilteredPlugins(
        $names = [],
        $emailids = [],
        $type = 0,
        $tags = [],
        $order = '',
        $limit = 0,
        $fulltext = ''
    ) {
        $fulltext = trim($fulltext);

        $maxpop = $this->getMaxPopularity();

        // default to all extensions
        if ($type == 0) {
            foreach (array_keys($this->types) as $t) {
                $type += $t;
            }
        }

        // cleanup $order
        $order = preg_replace('/[^a-z]+/', '', $order);
        if ($order == 'popularity') {
            $order .= ' DESC';
        }
        if ($order == 'lastupdate') {
            $order .= ' DESC';
        }
        if ($order == '') {
            if ($fulltext) {
                $order = 'score DESC';
            } else {
                $order = 'plugin';
            }
        }

        // limit
        $limit = (int) $limit;
        if ($limit) {
            $limit = "LIMIT $limit";
        } elseif ($fulltext) {
            $limit = 'LIMIT 50';
        } else {
            $limit = '';
        }

        // name filter
        $namefilter = '';
        $nameparams = [];
        if ($names) {
            $count = 0;
            foreach ($names as $name) {
                $nameparams[':name' . $count++] = $name;
            }

            $namefilter = 'AND A.plugin IN (' . implode(',', array_keys($nameparams)) . ')';
        }

        // email filter
        $emailfilter = '';
        $emailparams = [];
        if ($emailids) {
            $count = 0;
            foreach ($emailids as $email) {
                $emailparams[':email' . $count++] = $email;
            }

            $emailfilter = 'AND MD5(LOWER(A.email)) IN (' . implode(',', array_keys($emailparams)) . ')';
        }

        // tag filter
        $tagfilter = '';
        $tagparams = [];
        if ($tags) {
            $count = 0;
            foreach ($tags as $tag) {
                $tagparams[':tag' . $count++] = $tag;
            }

            $tagfilter = 'AND B.tag IN (' . implode(',', array_keys($tagparams)) . ')';
        }

        // fulltext search
        $fulltextwhere  = '';
        $fulltextfilter = '';
        $fulltextparams = [];
        if ($fulltext) {
            $fulltextwhere  = 'MATCH (A.plugin, A.name, A.description, A.author, A.tags)
                                 AGAINST (:fulltext WITH QUERY EXPANSION) AS score,';
            $fulltextfilter = 'AND MATCH (A.plugin, A.name, A.description, A.author, A.tags)
                                 AGAINST (:fulltext WITH QUERY EXPANSION)';
            $fulltextparams = [':fulltext' => $fulltext];
        }

        $obsoletefilter = "AND '" . $this->obsoleteTag . "' NOT IN(SELECT tag
                                                                   FROM plugin_tags
                                                                   WHERE plugin_tags.plugin = A.plugin)";

        $sql = "SELECT A.*,
                       A.popularity/:maxpop as popularity,
                       MD5(LOWER(A.email)) as emailid,
                       $fulltextwhere
                       GROUP_CONCAT(DISTINCT B.tag ORDER BY B.tag SEPARATOR '\n') as tags,
                       GROUP_CONCAT(DISTINCT C.other ORDER BY C.other SEPARATOR '\n') as similar,
                       GROUP_CONCAT(DISTINCT D.other ORDER BY D.other SEPARATOR '\n') as depends,
                       GROUP_CONCAT(DISTINCT E.other ORDER BY E.other SEPARATOR '\n') as conflicts
                  FROM plugins A
             LEFT JOIN plugin_tags B
                    ON A.plugin = B.plugin
             LEFT JOIN plugin_similar C
                    ON A.plugin = C.plugin
             LEFT JOIN plugin_depends D
                    ON A.plugin = D.plugin
             LEFT JOIN plugin_conflicts E
                    ON A.plugin = E.plugin

                 WHERE (A.type & :type)
                       $namefilter
                       $tagfilter
                       $emailfilter
                       $fulltextfilter
                       $obsoletefilter
              GROUP BY A.plugin
              ORDER BY $order
                       $limit";

        $db = $this->getPluginsDB();
        if (!$db) {
            throw new Exception('Cannot connect to database');
        }

        $parameters = array_merge(
            [':type' => $type, ':maxpop' => $maxpop],
            $nameparams,
            $tagparams,
            $emailparams,
            $fulltextparams
        );

        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);

        $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // add and cleanup few more fields
        $cnt = count($plugins);
        for ($i = 0; $i < $cnt; $i++) {
            if (in_array($plugins[$i]['plugin'], $this->bundled)) {
                $plugins[$i]['bundled'] = true;
            } else {
                $plugins[$i]['bundled'] = false;
            }
            if (!$plugins[$i]['screenshot']) {
                $plugins[$i]['screenshoturl'] = null;
                $plugins[$i]['thumbnailurl']  = null;
            } else {
                $plugins[$i]['screenshoturl'] = ml($plugins[$i]['screenshot'], '', true, '&', true);
                $plugins[$i]['thumbnailurl']  = ml($plugins[$i]['screenshot'], ['w' => 120, 'h' => 70], true, '&', true);
            }
            unset($plugins[$i]['screenshot']);
            unset($plugins[$i]['email']); // no spam

            $plugins[$i]['depends']   = array_filter(explode("\n", $plugins[$i]['depends']));
            $plugins[$i]['similar']   = array_filter(explode("\n", $plugins[$i]['similar']));
            $plugins[$i]['conflicts'] = array_filter(explode("\n", $plugins[$i]['conflicts']));
            $plugins[$i]['tags']      = array_filter(explode("\n", $plugins[$i]['tags']));

            $plugins[$i]['compatible'] = $this->cleanCompat($plugins[$i]['compatible']);
            $plugins[$i]['types']      = $this->listtypes($plugins[$i]['type']);

            $plugins[$i]['securitywarning'] = $this->replaceSecurityWarningShortcut($plugins[$i]['securitywarning']);

            ksort($plugins[$i]);
        }

        return $plugins;
    }

    /**
     * Translate sort keyword to sql clause
     * @param string $sort keyword in format [^]<columnnames|shortcut columnname>
     * @return string
     */
    private function getPluginsSortSql($sort)
    {
        $sortsql = '';
        if (str_starts_with($sort, '^')) {
            $sortsql = ' DESC';
            $sort    = substr($sort, 1);
        }
        if ($sort == 'a' || $sort == 'author') {
            $sortsql = 'ORDER BY author' . $sortsql;
        } elseif ($sort == 'd' || $sort == 'lastupdate') {
            $sortsql = 'ORDER BY lastupdate' . $sortsql;
        } elseif ($sort == 't' || $sort == 'type') {
            $sortsql = 'ORDER BY type' . $sortsql . ', simplename';
        } elseif ($sort == 'v' || $sort == 'compatibility') {
            $sortsql = 'ORDER BY bestcompatible' . $sortsql . ', simplename';
        } elseif ($sort == 'c' || $sort == 'popularity') {
            $sortsql = 'ORDER BY popularity' . $sortsql;
        } elseif ($sort == 'p' || $sort == 'plugin') {
            $sortsql = 'ORDER BY simplename' . $sortsql;
        } else {
            $sortsql = 'ORDER BY bestcompatible DESC, simplename' . $sortsql;
        }
        return $sortsql;
    }

    /**
     * @param string $id of plugin
     * @return array of metadata about plugin:
     *   'conflicts'  array of plugin names
     *   'similar'    array of plugin names
     *   'depends'    array of plugin names
     *   'needed'     array of plugin names
     *   'sameauthor' array of plugin names
     */
    public function getPluginRelations($id)
    {
        $db = $this->getPluginsDB();
        if (!$db) {
            return [];
        }

        $id   = strtolower($id);
        $meta = [
            'conflicts' => [],
            'similar' => [],
            'depends' => [],
            'needed' => [],
            'sameauthor' => [],
        ];

        /** @var PDOStatement $stmt */
        $stmt = $db->prepare(
            'SELECT plugin,other
            FROM plugin_conflicts
            WHERE plugin = ? OR other = ?'
        );
        $stmt->execute([$id, $id]);
        foreach ($stmt as $row) {
            if ($row['plugin'] == $id) {
                $meta['conflicts'][] = $row['other'];
            } elseif ($row['other'] == $id) {
                $meta['conflicts'][] = $row['plugin'];
            }
        }

        $stmt = $db->prepare(
            'SELECT plugin,other
            FROM plugin_similar
            WHERE plugin = ? OR other = ?'
        );
        $stmt->execute([$id, $id]);
        foreach ($stmt as $row) {
            if ($row['plugin'] == $id) {
                $meta['similar'][] = $row['other'];
            } elseif ($row['other'] == $id) {
                $meta['similar'][] = $row['plugin'];
            }
        }

        $stmt = $db->prepare(
            'SELECT other
            FROM plugin_depends
            WHERE plugin = ? '
        );
        $stmt->execute([$id]);
        foreach ($stmt as $row) {
            $meta['depends'][] = $row['other'];
        }

        $stmt = $db->prepare(
            'SELECT plugin
            FROM plugin_depends
            WHERE other = ? '
        );
        $stmt->execute([$id]);
        foreach ($stmt as $row) {
            $meta['needed'][] = $row['plugin'];
        }

        $stmt = $db->prepare(
            'SELECT plugin
            FROM plugins
            WHERE plugin <> ? AND email <> "" AND email=(SELECT email
                                                         FROM plugins
                                                         WHERE plugin = ?)'
        );
        $stmt->execute([$id, $id]);
        foreach ($stmt as $row) {
            $meta['sameauthor'][] = $row['plugin'];
        }
        if (!empty($meta['conflicts'])) {
            $meta['conflicts'] = array_unique($meta['conflicts']);
        }
        if (!empty($meta['similar'])) {
            $meta['similar'] = array_unique($meta['similar']);
        }
        return $meta;
    }

    /**
     * Return array of tags and their frequency in the repository
     *
     * @param int $minlimit
     * @param array $filter with entries:
     *                  'showall' => bool,
     *                  'plugintype' => 32 or different type,
     *                  'includetemplates' => bool
     * @return array with tags and counts
     */
    public function getTags($minlimit, $filter)
    {
        $db = $this->getPluginsDB();
        if (!$db) {
            return [];
        }

        if ($filter['showall']) {
            $shown = "1";
        } else {
            $shown = "'" . $this->obsoleteTag . "' NOT IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = B.plugin)
                      AND B.securityissue = ''";
        }
        if ($filter['plugintype'] == 32) {
            $shown .= ' AND B.type = 32';
        } elseif (!$filter['includetemplates']) {
            $shown .= ' AND B.type <> 32';
        }

        /** @var PDOStatement $stmt */
        $stmt = $db->prepare(
            "SELECT A.tag, COUNT(A.tag) as cnt
                    FROM plugin_tags as A, plugins as B
                   WHERE A.plugin = B.plugin
                     AND $shown
                GROUP BY tag
                  HAVING cnt >= :minlimit
                ORDER BY cnt DESC"
        );

        $stmt->bindParam(':minlimit', $minlimit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return number of installations for most popular plugin
     * besides the bundled ones
     * Otherwise 1 is return, to prevent dividing by zero. (and it correspondence with the usage of the author self)
     *
     * @param string $type either 'plugins' or 'templates', '' shows all
     * @return int
     */
    public function getMaxPopularity($type = '')
    {
        $db = $this->getPluginsDB();
        if (!$db) {
            return 1;
        }

        $sql = "SELECT A.popularity
                  FROM plugins A
                 WHERE '" . $this->obsoleteTag . "' NOT IN(SELECT tag
                                                           FROM plugin_tags
                                                           WHERE plugin_tags.plugin = A.plugin)";


        $sql .= str_repeat("AND plugin != ? ", count($this->bundled));

        if ($type == 'plugins' || $type == 'plugin') {
            $sql .= "AND plugin NOT LIKE 'template:%'";
        }
        if ($type == 'templates' || $type == 'template') {
            $sql .= "AND plugin LIKE 'template:%'";
        }

        $sql .= "ORDER BY popularity DESC
                 LIMIT 1";

        /** @var PDOStatement $stmt */
        $stmt = $db->prepare($sql);
        $stmt->execute($this->bundled);

        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $retval = $res[0]['popularity'];
        if (!$retval) {
            $retval = 1;
        }
        return (int) $retval;
    }

    /**
     * Delete all information about plugin from repository database
     * (popularity data is left intact)
     *
     * @param string $plugin extension id e.g. pluginname or template:templatename
     */
    public function deletePlugin($plugin)
    {
        $db = $this->getPluginsDB();
        if (!$db) {
            return;
        }

        /** @var PDOStatement $stmt */
        $stmt = $db->prepare('DELETE FROM plugins          WHERE plugin = ?');
        $stmt->execute([$plugin]);

        $stmt = $db->prepare('DELETE FROM plugin_tags      WHERE plugin = ?');
        $stmt->execute([$plugin]);

        $stmt = $db->prepare('DELETE FROM plugin_similar   WHERE plugin = ? OR other = ?');
        $stmt->execute([$plugin, $plugin]);

        $stmt = $db->prepare('DELETE FROM plugin_conflicts WHERE plugin = ? OR other = ?');
        $stmt->execute([$plugin, $plugin]);

        $stmt = $db->prepare('DELETE FROM plugin_depends   WHERE plugin = ? OR other = ?');
        $stmt->execute([$plugin, $plugin]);
    }

    /**
     * render internallink to plugin/template, templates identified by having namespace
     *
     * @param Doku_Renderer_xhtml $R
     * @param string $plugin pluginname
     * @param string|null $title Title of plugin link
     * @return string rendered internallink
     */
    public function pluginlink(Doku_Renderer_xhtml $R, string $plugin, string $title = null): string
    {
        if (!getNS($plugin)) {
            return $R->internallink(':plugin:' . $plugin, $title, null, true);
        } else {
            if (!$title) {
                $title = noNS($plugin);
            }
            return $R->internallink(':' . $plugin, $title, null, true);
        }
    }

    /**
     * Returns the recent DokuWiki releases
     *
     * @return array with entries: date =>['date'=>,'name'=>,'label'=>]
     */
    public function getDokuReleases()
    {
        if (!$this->dokuReleases) {
            $this->dokuReleases = [];
            $releases = explode(',', $this->getConf('releases'));
            $releases = array_map('trim', $releases);
            $releases = array_filter($releases);
            foreach ($releases as $release) {
                [$date, $name] = array_pad(preg_split('/(\s+"\s*|")/', $release), 2, '');
                $name = strtolower($name);
                $rel = [
                    'date' => $date,
                    'name' => $name
                ];
                $rel['label'] = ($name ? '"' . ucwords($name) . '"' : '');
                $this->dokuReleases[$date] = $rel;
            }
        }
        return $this->dokuReleases;
    }
    /**
     * Return array of supported DokuWiki releases
     * only releases mentioned in config are reported
     * 'newest' supported release at [0]
     *
     * @param string $compatible             raw compatibility text
     * @param bool   $onlyCompatibleReleases don't include not-compatible releases
     * @return array
     */
    public function cleanCompat($compatible, $onlyCompatibleReleases = true)
    {

        preg_match_all('/(!?\d\d\d\d-\d\d-\d\d\+?|!?[a-z A-Z]{4,}\+?)/', $compatible, $matches);
        $matches[0] = array_map('strtolower', $matches[0]);
        $matches[0] = array_map('trim', $matches[0]);
        $retval = [];
        $implicitCompatible = false;
        $nextImplicitCompatible = false;
        $dokuReleases = $this->getDokuReleases();
        ksort($dokuReleases);
        foreach ($dokuReleases as $release) {
            $isCompatible = true;
            if (in_array('!' . $release['date'], $matches[0]) || in_array('!' . $release['name'], $matches[0])) {
                $isCompatible = false;
                // stop implicit compatibility
                $nextImplicitCompatible = false;
                $implicitCompatible = false;
            } elseif (in_array($release['date'] . '+', $matches[0]) || in_array($release['name'] . '+', $matches[0])) {
                $nextImplicitCompatible = true;
            }
            if (
                $nextImplicitCompatible || !$isCompatible || in_array($release['date'], $matches[0])
                || in_array($release['name'], $matches[0]) || $implicitCompatible
            ) {
                if (!$onlyCompatibleReleases || $isCompatible) {
                    $retval[$release['date']]['label'] = $release['label'];
                    $retval[$release['date']]['implicit'] = $implicitCompatible;
                    $retval[$release['date']]['isCompatible'] = $isCompatible;
                }
            }
            if ($nextImplicitCompatible) {
                $implicitCompatible = true;
            }
        }
        krsort($retval);
        return $retval;
    }

    /**
     * @param bool $addInfolink
     * @return string rendered
     */
    public function renderCompatibilityHelp($addInfolink = false)
    {
        $link = wl('extension_compatibility');
        $infolink = '<sup>'
            . '<a href="' . $link . '" title="' . $this->getLang('compatible_with_info') . '">?</a>'
            . '</sup>';
        $infolink = $addInfolink ? $infolink : '';
        return sprintf($this->getLang('compatible_with'), $infolink);
    }

    /**
     * Clean list of plugins, return rendered as internallinks
     * input may be comma separated or array
     *
     * @param string|array $plugins
     * @param Doku_Renderer_xhtml $R
     * @param string $sep
     * @return string
     */
    public function listplugins($plugins, $R, $sep = ', ')
    {
        if (!is_array($plugins)) {
            $plugins = explode(',', $plugins);
        }
        $plugins = array_map('trim', $plugins);
        $plugins = array_map('strtolower', $plugins);
        $plugins = array_unique($plugins);
        $plugins = array_filter($plugins);
        sort($plugins);
        $out = [];
        foreach ($plugins as $plugin) {
            $out[] = $this->pluginlink($R, $plugin);
        }
        return implode($sep, $out);
    }

    /**
     * Convert comma separated list of tags to filterlinks
     *
     * @param string $string comma separated list of tags
     * @param string $target page id
     * @param string $sep
     * @return string
     */
    public function listtags($string, $target, $sep = ', ')
    {
        $tags = $this->parsetags($string);
        $out  = [];
        foreach ($tags as $tag) {
            $url = wl($target, ['plugintag' => $tag]) . '#extension__table';
            $out[] = '<a href="' . $url . '" class="wikilink1" title="List all plugins with this tag">'
                . hsc($tag)
                . '</a>';
        }
        return implode($sep, $out);
    }

    /**
     * Clean comma separated list of tags, return as sorted array
     *
     * @param string $string comma separated list of tags
     * @return array
     */
    public function parsetags($string)
    {
        $tags = preg_split('/[;,\s]/', $string);
        $tags = array_map('strtolower', $tags);
        $tags = array_unique($tags);
        $tags = array_filter($tags);
        sort($tags);
        return $tags;
    }

    /**
     * Convert $type (int) to list of filterlinks
     *
     * @param int    $type
     * @param string $target page id
     * @param string $sep
     * @return string
     */
    public function listtype($type, $target, $sep = ', ')
    {
        $types = [];
        foreach ($this->types as $k => $v) {
            if ($type & $k) {
                $url = wl($target, ['plugintype' => $k]) . '#extension__table';
                $types[] = '<a href="' . $url . '" class="wikilink1" title="List all ' . $v . ' plugins">'
                        . $v
                        . '</a>';
            }
        }
        sort($types);
        return implode($sep, $types);
    }

    /**
     * Convert $type (int) to array of names
     *
     * @param int $type
     * @return array
     */
    public function listtypes($type)
    {
        $types = [];
        foreach ($this->types as $k => $v) {
            if ($type & $k) {
                $types[] = $v;
            }
        }
        sort($types);
        return $types;
    }

    /**
     * Convert plugin type name (comma sep. string) to (int)
     *
     * @param string $types
     * @return int
     */
    public function parsetype($types)
    {
        $type = 0;
        foreach ($this->types as $k => $v) {
            if (preg_match('#' . preg_quote($v) . '#i', $types)) {
                $type += $k;
            }
        }
        if ($type === 0 && $types === '') {
            $type = 512; // CSS/JS-only
        }
        return $type;
    }

    /**
     * Create tables for repository
     *
     * @param PDO $db
     */
    private function initPluginDB($db)
    {
        msg("Repository plugin: data tables created for plugin repository", -1);

        $db->exec('CREATE TABLE plugin_conflicts (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_depends (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_similar (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_tags (plugin varchar(50) NOT NULL, tag varchar(255) NOT NULL);');
        $db->exec('CREATE TABLE plugins (plugin varchar(50) PRIMARY KEY NOT NULL, name varchar(255) default NULL,
            description varchar(255) default NULL, author varchar(255) default NULL, email varchar(255) default NULL,
            compatible varchar(255) default NULL, lastupdate date default NULL, downloadurl varchar(255) default NULL,
            bugtracker varchar(255) default NULL, sourcerepo varchar(255) default NULL,
            donationurl varchar(255) default NULL, type int(11) NOT NULL default 0,
            screenshot varchar(255) default NULL, tags varchar(255) default NULL,
            securitywarning varchar(255) default NULL, securityissue varchar(255) NOT NULL,
            bestcompatible varchar(50) default NULL, popularity int default 0,
            updatemessage varchar(50) default NULL);');
    }

    /**
     * Return security warning with replaced shortcut, if any.
     * If not, return original warning.
     *
     * @param string $warning Original warning content
     * @return string
     */
    public function replaceSecurityWarningShortcut($warning)
    {
        if (in_array($warning, $this->securitywarning)) {
            return $this->getLang('security_' . $warning);
        }
        return hsc($warning);
    }
}
