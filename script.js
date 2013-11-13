/**
 * AJAX functions for the pagename quicksearch
 *
 * @license  GPL2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Andreas Gohr <andi@splitbrain.org>
 * @author   Adrian Lang <lang@cosmocode.de>
 */
jQuery(function () {
    var timer = null;

    var $inObj  = jQuery('#qsearch2__in');
    var $outObj = jQuery('#qsearch2__out');
    var $formObj = jQuery('#dw__search2');
    var $nsObj = jQuery('#dw__ns');

    // objects found?
    if ($inObj.length === 0){ return; }
    if ($outObj.length === 0){ return; }
    if ($formObj.length === 0){ return; }
    if ($nsObj.length === 0){ return; }

    $inObj.attr("autocomplete", "off");

    function clear_results(){
        $outObj.hide();
        $outObj.html('');
    }

    var onCompletion = function (responseText) {
        if (responseText === '') { return; }

        $outObj.html(responseText);
        $outObj.show();
    };

    var performSearch = function () {
        clear_results();
        var value = $inObj.val();
        if(value === ''){ return; }
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'qsearch',
                q: value + ' @' + $nsObj.val()
            },
            onCompletion
        );
    };

    // attach eventhandler to search field
    $inObj.keyup(function () {
        clear_results();
        if(timer){
            window.clearTimeout(timer);
            timer = null;
        }
        timer = window.setTimeout(performSearch, 500);
    });

    // attach eventhandler to output field
    $outObj.click(function () {
        $outObj.hide();
    });

    $formObj.submit(function () {
        $inObj.val($inObj.val() + ' @' + $nsObj.val());
        return true;
    });
});
