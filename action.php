<?php
/**
 * Removes entries from repository database when removed from page
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */

if(!defined('DOKU_INC')) die();

/**
 * Register actions for event hooks
 */
class action_plugin_pluginrepo extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, '_cleanOldEntry');
    }

    /**
     * Handles the page write event and removes the database info
     * when the plugin or template code is no longer in the source
     *
     * @param Doku_Event $event  event object by reference
     * @param null $param  empty
     */
    public function _cleanOldEntry(&$event, $param) {
        global $ID;

        //only in relevant namespaces
        if(!(curNS($ID) == 'plugin' || curNS($ID) == 'template')) return;


        $data = $event->data;
        $haspluginentry = preg_match('/----+ *plugin *-+/', $data[0][1]);     // addSpecialPattern: ----+ *plugin *-+\n.*?\n----+
        $hastemplateentry = preg_match('/----+ *template *-+/', $data[0][1]); // addSpecialPattern: ----+ *template *-+\n.*?\n----+
        if($haspluginentry || $hastemplateentry) return; // plugin seems still to be there

        $hlp = $this->loadHelper('pluginrepo');
        if(!$hlp) return;

        if(curNS($ID) == 'plugin') {
            $id = noNS($ID);
        } else {
            $id = curNS($ID) . ':' . noNS($ID);
        }

        $hlp->deletePlugin($id);
    }
}
