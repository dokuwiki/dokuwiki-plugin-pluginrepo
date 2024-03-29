<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/**
 * Removes entries from repository database when removed from page
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */
/**
 * Register actions for event hooks
 */
class action_plugin_pluginrepo extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'cleanOldEntry');
    }

    /**
     * Handles the page write event and removes the database info
     * when the plugin or template code is no longer in the source
     *
     * @param Event $event event object
     */
    public function cleanOldEntry(Event $event)
    {
        global $ID;

        $data = $event->data;
        // addSpecialPattern: ----+ *plugin *-+\n.*?\n----+
        $haspluginentry = preg_match('/----+ *plugin *-+/', $data[0][1]);
        // addSpecialPattern: ----+ *template *-+\n.*?\n----+
        $hastemplateentry = preg_match('/----+ *template *-+/', $data[0][1]);
        if ($haspluginentry || $hastemplateentry) {
            return; // plugin seems still to be there
        }

        /** @var helper_plugin_pluginrepo_repository $hlp */
        $hlp = $this->loadHelper('pluginrepo_repository');
        if (!$hlp) {
            return;
        }

        if (curNS($ID) == 'plugin') {
            $id = noNS($ID);
        } else {
            $id = curNS($ID) . ':' . noNS($ID);
        }

        $hlp->deletePlugin($id);
    }
}
