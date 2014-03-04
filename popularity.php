<?php

if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__) . '/../../../');
require_once(DOKU_INC . 'inc/init.php');

require_once(DOKU_PLUGIN . 'pluginrepo/helper/repository.php');

$key = $INPUT->str('key'); //e.g. page_size, media_size, webserver, php_version, etc
if(!$key) {
    echo 'no key given';
    return;
}

/** @var helper_plugin_pluginrepo_popularity $POPULARITY */
$POPULARITY = plugin_load('helper', 'pluginrepo_popularity');
if(!$POPULARITY) {
    echo 'no popularity helper available';
    return;
}

$output = $INPUT->str('output', 'html', true); // rss, pie, line, html

//check for time limits
$startdate = $INPUT->str('s');
$enddate   = $INPUT->str('e');
$daysago   = $INPUT->int('d');

$default = ($output == 'line') ? 'val' : 'cnt';
$orderby   = $INPUT->str('o', $default, true);

//retrieve data
$counts = $POPULARITY->getCounts($key, $orderby, $startdate, $enddate, $daysago);

// build output
$limit = $INPUT->int('limit', 5, true);
$w = $INPUT->int('w', 450, true);
$h = $INPUT->int('h', 180, true);

$default = ($output == 'line') ? false : true;
$usepercentage = $INPUT->bool('p', $default);

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
function output($counts, $output, $usepercentage, $limit, $w, $h) {

    switch($output) {
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
function redirects_googlecharts($counts, $output, $usepercentage, $limit, $w, $h) {
    global $POPULARITY;

    $max = $POPULARITY->getNumberOfSubmittingWikis();

    $data = array();
    $label = array();
    $other = 0;

    $cnt = 0;
    foreach($counts as $count) {
        if($limit > 0 AND ++$cnt > $limit) {
            $other += $count['cnt'];
        } else {
            $data[] = formatNumber($count['cnt'], $usepercentage, $max, false);
            $labeltext = $count['val'];
            if($output == 'pie') {
                $labeltext .= '  ' . formatNumber($count['cnt'], $usepercentage, $max);
            }
            $label[] =  $labeltext;
        }
    }
    $data[] = formatNumber($other, $usepercentage, $max, false);
    $labeltext = 'other';
    if($output == 'pie') {
        $labeltext .= '  ' . formatNumber($other, $usepercentage, $max);
    }
    $label[] =  $labeltext;
    $label = array_map('rawurlencode', $label);

    // Create query
    if($output == 'pie') {
        //pie chart
        $query = array(
            'cht' => 'p',                         // Type
            'chs' => $w . 'x' . $h,               // Size
            'chco'=> '4d89f9',                    // Serie colors
            'chf' => 'bg,s,ffffff00',             // 'a,s,ffffff' // Background color  (bg=background, a=transparant, s=solid)
            'chds'=> $usepercentage ? null : 'a', // automatically scaling, needed for absolute data values
            'chd' => 't:' . join(',', $data),     // Data
            'chl' => join('|', $label)            // Data labels
        );
    } else {
        //line chart
        $query = array(
            'cht' => 'lc',                        // Type
            'chs' => $w . 'x' . $h,               // Size
            'chco'=> '4d89f9',                    // Serie colors
            'chf' => 'bg,s,ffffff00',             // 'a,s,ffffff' // Background color  (bg=background, a=transparant, s=solid)
            'chxt' =>  true ? 'x,y' : null,       // X & Y axis labels
            'chds'=> $usepercentage ? null : 'a', // scaling: automatically
            'chd' => 't:' . join(',', $data),     // Data
            'chl' => join('|', $label)            // Data labels
        );
    }


    $url = 'http://chart.apis.google.com/chart?' . buildUnencodedURLparams($query);

    header('Location: '.$url);
}

///**
// * Redirects to Google charts api, which returns a png image of a line chart
// *
// * @param array $counts array with value - count pairs
// * @param int $limit number of values shown, rest as summarized as 'other'
// * @param int $w chart image width
// * @param int $h chart image height
// */
//function header_linechart($counts, $limit, $w, $h) {
//    $data = array();
//    $label = array();
//    $other = 0;
//
//    $cnt = 0;
//    foreach($counts as $count) {
//        if($limit > 0 AND ++$cnt > $limit) {
//            $other += $count['cnt'];
//        } else {
//            $data[] = $count['cnt'];
//            $label[] = $count['val'];
//        }
//    }
//    if($other > 0) {
//        $data[] = $other;
//        $label[] = 'other';
//    }
//
//    $label = array_map('rawurlencode', $label);
//
//    // Create query
//    $query = array(
//        'cht' => 'lc',                      // Type
//        'chs' => $w . 'x' . $h,             // Size
//        'chco'=> '4d89f9',                  // Serie colors
//        'chf' => 'bg,s,ffffff00',           // 'a,s,ffffff' // Background color  (bg=background, a=transparant, s=solid)
//        'chxt' =>  true ? 'x,y' : null,     // X & Y axis labels
//        'chds'=> 'a',                       // scaling: automatically
//        'chd' => 't:' . join(',', $data),   // Data
//        'chl' => join('|', $label)          // Data labels
//    );
//
//    $url = 'http://chart.apis.google.com/chart?' . buildUnencodedURLparams($query);
//
//    header('Location: ' . $url);
//}

/**
 * Build an string of URL parameters
 * (Based on buildURLparams())
 *
 * @param $params
 * @param string $sep
 * @return string
 */
function buildUnencodedURLparams($params, $sep='&') {
    $url = '';
    $amp = false;
    foreach($params as $key => $val) {
        if($amp) $url .= $sep;

        $url .= rawurlencode($key).'=';
        $url .= (string) $val;
        $amp = true;
    }
    return $url;
};

/**
 * Format the number, as percentage or number
 *
 * @param int  $value
 * @param bool $calculatepercentage
 * @param int  $max                the maximum value
 * @param bool $withpercentagechar add % behind number?
 * @return string
 */
function formatNumber($value, $calculatepercentage = true, $max = null, $withpercentagechar = true) {
    if($calculatepercentage && $max) {
        $char = $withpercentagechar ? '%%' : '';
        return sprintf('%.1f' . $char , $value * 100 / $max);
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
function html_table($counts, $usepercentage, $limit) {
    global $POPULARITY;

    $max = $POPULARITY->getNumberOfSubmittingWikis();

    $cnt = 0;
    $other = 0;

    echo '<table>';
    foreach($counts as $count) {
        if($limit > 0 AND ++$cnt > $limit) {
            $other += $count['cnt'];
        } else {
            echo '<tr>';
            echo '    <td>' . htmlspecialchars($count['val']) . '</td>';
            echo '    <td>' . formatNumber($count['cnt'], $usepercentage, $max) . '</td>';
            echo '</tr>';
        }
    }
    if($other > 0) {
        echo '<tr>';
        echo '    <td>Other</td>';
        echo '    <td>' . formatNumber($other, $usepercentage, $max) . '</td>';
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
function xml_rss($counts, $usepercentage, $limit) {
    global $POPULARITY;

    $max = $POPULARITY->getNumberOfSubmittingWikis();

    $cnt = 0;
    $other = 0;

    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="utf-8"?>' . NL;
    echo '<rss version="0.91">' . NL;
    echo '<channel>' . NL;
    foreach($counts as $count) {
        if($limit > 0 AND ++$cnt > $limit) {
            $other += $count['cnt'];
        } else {
            echo '  <item>' . NL;
            echo '      <title>' . formatNumber($count['cnt'], $usepercentage, $max) . ' ' . htmlspecialchars($count['val']) . '</title>' . NL;
            echo '  </item>' . NL;
        }
    }
    if($other) {
        echo '  <item>' . NL;
        echo '      <title>' . formatNumber($other, $usepercentage, $max) . ' other</title>' . NL;
        echo '  </item>' . NL;
    }
    echo '</channel>';
    echo '</rss>';
}