<?php
/**
 * English language file for pluginrepo plugin
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  H�kan Sandell <sandell.hakan@gmail.com>
 */

// Plugin entry
$lang['by']                  = 'by';
$lang['last_updated_on']     = 'Last updated on';
$lang['provides']            = 'Provides';
$lang['compatible_with']     = 'Compatible with DokuWiki%s';
$lang['compatible_with_info']= 'Please update this field';
$lang['no_compatibility']    = 'No compatibility info given!';
$lang['compatible_unknown']  = 'unknown';
$lang['compatible_yes']      = 'yes';
$lang['compatible_probably'] = 'probably';
$lang['develonly']           = 'Devel only';
$lang['conflicts_with']      = 'Conflicts with';
$lang['requires']            = 'Requires';
$lang['similar_to']          = 'Similar to';
$lang['tagged_with']         = 'Tagged with';
$lang['needed_for']          = 'Needed for';
$lang['securitywarning']     = 'Security warning (please read %s):';
$lang['security_informationleak'] = 'This plugin expose information that might be valuable to a hacker. It is not recommended in a public installation.';
$lang['security_allowsscript']    = 'This plugin will allow execution of scripts. It should only be used when you trust ALL editors, best suited in private personal wikis.';
$lang['security_requirespatch']   = 'The plugin requires patching the DokuWiki core. Manual patches may break compatibility with other plugins and make it harder to secure your installation by upgrading to latest version.';
$lang['security_partlyhidden']    = 'Hiding parts of a DokuWiki page is not supported by the core. Most attempts to introduce ACL control for parts of a page will leak information through RSS feed, search or other core functionality.';
$lang['securityissue']       = 'The following security issue was reported for this plugin:';
$lang['securityrecommendation']   = 'It is not recommended to use this plugin until this issue was fixed. Plugin authors should read the %s';
$lang['securitylink']        = 'plugin security guidelines';
$lang['name_underscore']     = 'Plugin name contains underscore, will not generate popularity points.';
$lang['downloadurl']         = 'Download';
$lang['bugtracker']          = 'Report bugs';
$lang['sourcerepo']          = 'Repository';
$lang['source']              = 'Source';
$lang['donationurl']         = 'Donate';

// Plugin table
$lang['t_search_plugins']    = 'Search Plugins';
$lang['t_search_template']   = 'Search Templates';
$lang['t_searchintro_plugins'] = 'Filter available plugins by type or by using the tag cloud. You could also search within the plugin namespace using the search box.';
$lang['t_searchintro_template']= 'Filter available templates by using the tag cloud. You could also search within the template namespace using the search box.';
$lang['t_btn_search']        = 'Search';
$lang['t_btn_searchtip']     = 'Search within namespace';
$lang['t_filterbytype']      = 'Filter by type';
$lang['t_typesyntax']        = '%s plugins extend DokuWiki\'s basic syntax.';
$lang['t_typeaction']        = '%s plugins replace or extend DokuWiki\'s core functionality';
$lang['t_typeadmin']         = '%s plugins provide extra administration tools';
$lang['t_typerender']        = '%s plugins add new export modes or replaces the standard XHTML renderer';
$lang['t_typehelper']        = '%s plugins provide functionality shared by other plugins';
$lang['t_typetemplate']      = '%s changes the look and feel of DokuWiki';
$lang['t_typeremote']        = '%s plugins add methods to the RemoteAPI accessible via web services';
$lang['t_typeauth']          = '%s plugins add authentication modules';
$lang['t_filterbytag']       = 'Filter by tag';
$lang['t_availabletype']     = 'Available %s plugins';
$lang['t_availabletagged']   = 'Tagged with \'%s\'';
$lang['t_availableplugins']  = 'All available';
$lang['t_jumptoplugins']     = 'Jump to first plugin starting with:';
$lang['t_resetfilter']       = 'Show all (remove filter/sort)';
$lang['t_oldercompatibility'] = 'Compatible with older DokuWiki versions';

$lang['t_name_plugins']      = 'Plugin';
$lang['t_name_template']     = 'Template';
$lang['t_sortname']          = 'Sort by name';
$lang['t_description']       = 'Description';
$lang['t_author']            = 'Author';
$lang['t_sortauthor']        = 'Sort by author';
$lang['t_type']              = 'Type';
$lang['t_sorttype']          = 'Sort by type';
$lang['t_date']              = 'Last Update';
$lang['t_sortdate']          = 'Sort by date';
$lang['t_popularity']        = 'Popularity';
$lang['t_sortpopularity']    = 'Sort by popularity';
$lang['t_compatible']        = 'Last Compatible';
$lang['t_sortcompatible']    = 'Sort by compatibility';
$lang['t_screenshot']        = 'Screenshot';

$lang['t_download']          = 'Download';
$lang['t_provides']          = 'Provides';
$lang['t_tags']              = 'Tags';
$lang['t_bundled']           = 'bundled';


//Setup VIM: ex: et ts=2 enc=utf-8 :
