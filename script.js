/**
 * AJAX functions for the pagename quicksearch
 *
 * @license  GPL2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Andreas Gohr <andi@splitbrain.org>
 * @author   Adrian Lang <lang@cosmocode.de>
 */

var pr_qsearch = {
    $inObj: null,
    $outObj: null,
    $nsObj: null,
    $formObj: null,
    timer: null,

    init: function () {
        pr_qsearch.$inObj = jQuery('#qsearch2__in');
        pr_qsearch.$outObj = jQuery('#qsearch2__out');
        pr_qsearch.$formObj = jQuery('#dw__search2');
        pr_qsearch.$nsObj = jQuery('#dw__ns');

        // objects found?
        if (pr_qsearch.$inObj.length === 0 ||
            pr_qsearch.$outObj.length === 0 ||
            pr_qsearch.$formObj.length === 0 ||
            pr_qsearch.$nsObj.length === 0) {
            return;
        }

        pr_qsearch.$inObj.attr("autocomplete", "off");

        // attach eventhandler to search field
        pr_qsearch.$inObj.keyup(function () {
            pr_qsearch.clear_results();
            if (pr_qsearch.timer) {
                window.clearTimeout(pr_qsearch.timer);
                pr_qsearch.timer = null;
            }
            pr_qsearch.timer = window.setTimeout(pr_qsearch.performSearch, 500);
        });

        // attach eventhandler to output field
        pr_qsearch.$outObj.click(function () {
            pr_qsearch.$outObj.hide();
        });

        pr_qsearch.$formObj.submit(function () {
            pr_qsearch.$inObj.val(pr_qsearch.$inObj.val() + ' @' + pr_qsearch.$nsObj.val());
            return true;
        });
    },

    clear_results: function () {
        pr_qsearch.$outObj.hide();
        pr_qsearch.$outObj.html('');
    },

    onCompletion: function (responseText) {
        if (responseText === '') return;

        pr_qsearch.$outObj.html(responseText);
        pr_qsearch.$outObj.show();
    },

    performSearch: function () {
        pr_qsearch.clear_results();
        var value = pr_qsearch.$inObj.val();
        if (value === '') return;

        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'qsearch',
                q: value + ' @' + pr_qsearch.$nsObj.val()
            },
            pr_qsearch.onCompletion
        );
    }
};


jQuery(function () {
    pr_qsearch.init();
});
