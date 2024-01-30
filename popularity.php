<?php

/**
 * Creates output in different format of the usage statistics of DokuWiki
 *
 * Available outputs: rss-feed, png pie-chart, png line-chart, html table
 *
 * Call url: [wiki]/lib/plugins/popularity.php?[parameters]
 * Example: lib/plugins/pluginrepo/popularity.php?key=php_version&output=line&limit=6&w=500
 *
 * Parameters:
 *  - output: rss, pie, line, table(default)
 *  - key: submitted fields by popularity plugin (required)
 *        e.g. page_size, media_size, webserver, php_version, attic_avg, attic_biggest, attic_count, attic_oldest,
 *         attic_size, attic_smallest, cache_avg, cache_biggest, cache_count, cache_size, cache_smallest, conf_authtype,
 *         conf_template, conf_useacl, edits_per_day, index_avg, index_biggest, index_count, index_size, index_smallest,
 *         language, media_avg, media_biggest, media_count, media_nscount, media_nsnest, media_smallest, meta_avg,
 *         meta_biggest, meta_count, meta_size, meta_smallest, now, os, page_avg, page_biggest, page_count,
 *         page_nscount, page_nsnest, page_oldest, page_smallest, pcre_backtrack, pcre_recursion, pcre_version,
 *         php_exectime, php_extension, php_memory, php_sapi, plugin, popauto, popversion, user_count
 *  - limit: number of results shown, when more the rest is summarized as 'other'. -1 shows all results (default 5)
 *  - w:     image width  (only charts, default 450px)
 *  - h:     image height (only charts, default 180px)
 *  - o:     ordered by 'cnt' for counts (default) or 'val' for values ('val' is only default for line chart)
 *  - p:     if true use percentages(default), otherwise absolute numbers (absolute is only default for line chart)
 *  - s:     (optional) start date, show only submits after this date
 *  - e:     (optional) end date, when start date set, show only until this date
 *  - d:     (optional) when no start date set, shows the submits of last d days
 *
 */

if (!defined('DOKU_INC')) {
    define('DOKU_INC', __DIR__ . '/../../../');
}
require_once(DOKU_INC . 'inc/init.php');
require_once(DOKU_PLUGIN . 'pluginrepo/helper/repository.php');

//close session
session_write_close();

$key = $INPUT->str('key'); //e.g. page_size, media_size, webserver, php_version, etc
if (!$key) {
    echo 'no key given';
    return;
}

/** @var helper_plugin_pluginrepo_popularity $popularity */
$popularity = plugin_load('helper', 'pluginrepo_popularity');
if (!$popularity) {
    echo 'no popularity helper available';
    return;
}

$output = $INPUT->str('output', 'table', true); // rss, pie, line, table

//check for time limits
$startdate = $INPUT->str('s');
$enddate   = $INPUT->str('e');
$daysago   = $INPUT->int('d');

$default = ($output == 'line') ? 'val' : 'cnt';
$orderby   = $INPUT->str('o', $default, true);

$default = $output != 'line';
$usepercentage = $INPUT->bool('p', $default);

//retrieve data
$counts = $popularity->getCounts($key, $orderby, $startdate, $enddate, $daysago);
if ($usepercentage) {
    $MAX = $popularity->getNumberOfSubmittingWikis($startdate, $enddate, $daysago);
} else {
    $MAX = 0;
}

// build output
$limit = $INPUT->int('limit', 5, true);
$w = $INPUT->int('w', 450, true);
$h = $INPUT->int('h', 180, true);



output($counts, $output, $usepercentage, $limit, $w, $h);

/**
 * Creates desired output
 *
 * @param array $counts array with value - count pairs
 * @param string $output desired output format
 * @param bool $usepercentage (only supported by table)
 * @param int $limit number of values shown, rest as summarized as 'other'. Negative is no limit.
 * @param int $w chart image width
 * @param int $h chart image height
 */
function output($counts, $output, $usepercentage, $limit, $w, $h)
{

    switch ($output) {
        case 'rss':
            xml_rss($counts, $usepercentage, $limit);
            break;
        case 'pie':
            redirects_googlecharts($counts, 'pie', $usepercentage, $limit, $w, $h);
            break;
        case 'line':
            redirects_googlecharts($counts, 'line', $usepercentage, $limit, $w, $h);
            break;
        default:
            html_table($counts, $usepercentage, $limit);
            break;
    }
}

/**
 * Redirects to Google charts api, which returns a png image of a pie chart
 *
 * @param array $counts array with value - count pairs
 * @param string $output 'pie' or 'line'
 * @param int $limit number of values shown, rest as summarized as 'other'
 * @param $usepercentage
 * @param int $w chart image width
 * @param int $h chart image height
 */
function redirects_googlecharts($counts, $output, $usepercentage, $limit, $w, $h)
{
    $data = [];
    $label = [];
    $other = 0;

    $cnt = 0;
    foreach ($counts as $count) {
        if ($limit > 0 && ++$cnt > $limit) {
            $other += $count['cnt'];
        } else {
            $data[] = formatNumber($count['cnt'], $usepercentage, false);
            $labeltext = $count['val'];
            if ($output == 'pie') {
                $labeltext .= '  ' . formatNumber($count['cnt'], $usepercentage);
            }
            $label[] =  $labeltext;
        }
    }
    if ($other > 0) {
        $data[] = formatNumber($other, $usepercentage, false);
        $labeltext = 'other';
        if ($output == 'pie') {
            $labeltext .= '  ' . formatNumber($other, $usepercentage);
        }
        $label[] =  $labeltext;
    }

    $label = array_map('rawurlencode', $label);

    // Create query
    if ($output == 'pie') {
        //pie chart
        $query = [
            'cht' => 'p',             // Type
            'chs' => $w . 'x' . $h,   // Size
            'chco' => '4d89f9',       // Serie colors
            'chf' => 'bg,s,ffffff00', // 'a,s,ffffff' // Background color  (bg=background, a=transparant, s=solid)
            'chds' => $usepercentage ? null : 'a',        // automatically scaling, needed for absolute data values
            'chd' => 't:' . implode(',', $data), // Data
            'chl' => implode('|', $label),       // Data labels
        ];
    } else {
        //line chart
        $query = [
            'cht' => 'lc',            // Type
            'chs' => $w . 'x' . $h,   // Size
            'chco' => '4d89f9',       // Serie colors
            'chf' => 'bg,s,ffffff00', // 'a,s,ffffff' // Background color  (bg=background, a=transparant, s=solid)
            'chxt' =>  true ? 'x,y' : null,               // X & Y axis labels
            'chds' => $usepercentage ? null : 'a',        // scaling: automatically
            'chd' => 't:' . implode(',', $data), // Data
            'chl' => implode('|', $label),       // Data labels
        ];
    }


    $url = 'https://chart.apis.google.com/chart?' . buildUnencodedURLparams($query);

    header('Location: ' . $url);
}

/**
 * Build an string of URL parameters
 * (Based on buildURLparams())
 *
 * @param $params
 * @param string $sep
 * @return string
 */
function buildUnencodedURLparams($params, $sep = '&')
{
    $url = '';
    $amp = false;
    foreach ($params as $key => $val) {
        if ($amp) {
            $url .= $sep;
        }

        $url .= rawurlencode($key) . '=';
        $url .= (string) $val;
        $amp = true;
    }
    return $url;
}

/**
 * Format the number, as percentage or number
 *
 * @param int  $value
 * @param bool $calculatepercentage
 * @param bool $withpercentagechar add % behind number?
 * @return string
 */
function formatNumber($value, $calculatepercentage = true, $withpercentagechar = true)
{
    global $MAX;

    if ($calculatepercentage && $MAX) {
        $char = $withpercentagechar ? '%%' : '';
        return sprintf('%.1f' . $char, $value * 100 / $MAX);
    } else {
        return $value;
    }
}

/**
 * Returns a simple html table
 *
 * @param array $counts array with value - count pairs
 * @param int $limit number of values shown, rest as summarized as 'other'
 * @param bool $usepercentage
 */
function html_table($counts, $usepercentage, $limit)
{

    $cnt = 0;
    $other = 0;

    echo '<table>';
    foreach ($counts as $count) {
        if ($limit > 0 && ++$cnt > $limit) {
            $other += $count['cnt'];
        } else {
            echo '<tr>';
            echo '    <td>' . htmlspecialchars($count['val']) . '</td>';
            echo '    <td>' . formatNumber($count['cnt'], $usepercentage) . '</td>';
            echo '</tr>';
        }
    }
    if ($other > 0) {
        echo '<tr>';
        echo '    <td>Other</td>';
        echo '    <td>' . formatNumber($other, $usepercentage) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

/**
 * Returns a rss feed of the counts
 *
 * @param array $counts array with value - count pairs
 * @param int $limit number of values shown, rest as summarized as 'other'
 * @param bool $usepercentage
 */
function xml_rss($counts, $usepercentage, $limit)
{

    $cnt = 0;
    $other = 0;

    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="utf-8"?>';
    echo '<rss version="0.91">';
    echo '<channel>';
    foreach ($counts as $count) {
        if ($limit > 0 && ++$cnt > $limit) {
            $other += $count['cnt'];
        } else {
            echo '<item>';
            echo '    <title>';
            echo formatNumber($count['cnt'], $usepercentage) . ' ' . htmlspecialchars($count['val']);
            echo '    </title>';
            echo '</item>';
        }
    }
    if ($other > 0) {
        echo '<item>';
        echo '    <title>' . formatNumber($other, $usepercentage) . ' other</title>';
        echo '</item>';
    }
    echo '</channel>';
    echo '</rss>';
}
