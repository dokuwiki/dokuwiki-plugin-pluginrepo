<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Gerrit <klapinklapin@gmail.com>
 * @author Rene <wllywlnt@yahoo.com>
 * @author Peter van Diest <peter.van.diest@xs4all.nl>
 */
$lang['by']                    = 'door';
$lang['last_updated_on']       = 'Laatste update op';
$lang['provides']              = 'Levert';
$lang['compatible_with']       = 'Compatibel met DokuWiki%s';
$lang['compatible_with_info']  = 'Update alsjeblieft dit veld';
$lang['no_compatibility']      = 'Geen compatibiliteitsinfo gegeven!';
$lang['compatible_unknown']    = 'onbekend';
$lang['compatible_yes']        = 'ja';
$lang['compatible_no']         = 'nee';
$lang['compatible_probably']   = 'waarschijnlijk';
$lang['develonly']             = 'Alleen voor ontwikkelaars';
$lang['conflicts_with']        = 'Conflicteert met';
$lang['requires']              = 'Vereist';
$lang['similar_to']            = 'Vergelijkbaar met';
$lang['tagged_with']           = 'Gelabeld met';
$lang['needed_for']            = 'Nodig voor';
$lang['securitywarning']       = 'Veiligheidswaarschuwing (Lees alstublieft %s):';
$lang['security_informationleak'] = 'Deze plugin onthult informatie die waardevol kan zijn voor hackers. Dus wordt daarom niet aanbevolen voor publieke installaties.';
$lang['security_allowsscript'] = 'Deze plugin zal het uitvoeren van scripts toestaan. Het zou alleen gebruikt moeten worden wanneer je ALLE schrijvers vertrouwt, dus deze past het beste in persoonlijke prive wiki\'s.';
$lang['security_requirespatch'] = 'Deze plugin vereist aanpassingen aan de DokuWiki-kern code. Handmatige aanpassingen kunnen de compatibiliteit met andere plugins verstoren en maken het lastiger om je installatie te upgraden naar de laatste versie en op die manier veilig te houden.';
$lang['security_partlyhidden'] = 'Verbergen van delen van een DokuWikipagina wordt niet standaard ondersteund door DokuWiki. De meeste pogingen om een ACL controle toe te voegen voor delen van een pagina zullen toch informatie lekken via RSS-feed, zoekfunctie of een andere basisfunctie.';
$lang['securityissue']         = 'Het volgende veiligheidsprobleem is gerapporteerd voor deze plugin:';
$lang['securityrecommendation'] = 'Het wordt niet aanbevolen om deze plugin te gebruiken tot dat dit probleem is gerepareerd. Pluginauteurs worden dringend aangeraden de %s te lezen';
$lang['securitylink']          = 'plugin beveiligingsrichtlijnen';
$lang['name_underscore']       = 'Pluginnaam bevat een lagestreep "_" en zal daarom geen populariteitspunten ontvangen.';
$lang['name_oldage']           = 'Deze uitbreiding is al meer dan 2 jaar niet meer geupdated. Misschien worden hij niet langer onderhouden of ondersteund. Er kunnen compatibiliteitsproblemen zijn.';
$lang['extension_obsoleted']   = '<strong>Deze extensie is gemarkeerd als verouderd.</strong> Daarom is die verborgen in de Extensiebeheerder en extensieoverzichten. Bovendien, staat die op de nominatie verwijder te worden.';
$lang['missing_downloadurl']   = 'De ontbrekende download-link betekent dat deze extensie niet geïnstalleerd kan worden via de Extensiebeheerder. Kijk alstublieft op <a href="/devel:plugins#publishing_a_plugin_on_dokuwikiorg" class="wikilink1" title="devel:plugins">Publishing a Plugin on dokuwiki.org</a>. Het wordt aanbevolen om een publieke repository hosting zoals GitHub, GitLab of Bitbucket te gebruiken. ';
$lang['wrongnamespace']        = 'Deze extensie is niet in de \'plugin\' of \'template\' namespace en wordt daarom genegeerd.';
$lang['downloadurl']           = 'Download';
$lang['bugtracker']            = 'Meld bugs';
$lang['sourcerepo']            = 'Centrale opslag';
$lang['source']                = 'Broncode';
$lang['donationurl']           = 'Doneer';
$lang['more_extensions']       = 'en nog %d';
$lang['t_search_plugins']      = 'Zoek Plugins';
$lang['t_search_template']     = 'Zoek Templates';
$lang['t_searchintro_plugins'] = 'Filter de beschikbare plugins op type of gebruik de wolk met labels. Je kunt ook in de plugin-namespace zoeken met het zoekvakje.';
$lang['t_searchintro_template'] = 'Filter de beschikbare templates via wolk met labels. Je kunt ook in de template-namespace zoeken met het zoekvakje.';
$lang['t_btn_search']          = 'Zoek';
$lang['t_btn_searchtip']       = 'Zoek in de namespace';
$lang['t_filterbytype']        = 'Filter op type';
$lang['t_typesyntax']          = '%s plugins breiden DokuWiki\'s basissyntax uit.';
$lang['t_typeaction']          = '%s plugins vervangen of breiden DokuWiki\'s standaardfuncties uit';
$lang['t_typeadmin']           = '%s plugins leveren extra beheerfuncties';
$lang['t_typerender']          = '%s plugins voegen een nieuwe uitvoermode toe of vervangen de standaard XHTML-rendermachine';
$lang['t_typehelper']          = '%s plugins leveren functies die gedeeld wordt met andere plugins';
$lang['t_typetemplate']        = '%s wijzigen het uiterlijk en het gedrag van DokuWiki';
$lang['t_typeremote']          = '%s plugins voegen methodes toe aan de RemoteAPI toegankelijk via webdiensten';
$lang['t_typeauth']            = '%s plugins voegen authenticatiemodules toe';
$lang['t_typecli']             = '%s plugins voegen commando\'s toe die gebruikt kunnen worden in een opdrachtregelinterface (Command Line Interface/Cli)';
$lang['t_filterbytag']         = 'Filter op label';
$lang['t_availabletype']       = 'Beschikbare %s plugins';
$lang['t_availabletagged']     = 'Gelabeld met \'%s\'';
$lang['t_availableplugins']    = 'Alles beschikbaar';
$lang['t_jumptoplugins']       = 'Spring naar de eerste plugin die start met:';
$lang['t_resetfilter']         = 'Geef alles weer (verwijder filter/sortering)';
$lang['t_oldercompatibility']  = 'Compatibel met oudere DokuWiki versies';
$lang['t_name_plugins']        = 'Plugin';
$lang['t_name_template']       = 'Template';
$lang['t_sortname']            = 'Sorteer op naam';
$lang['t_description']         = 'Beschrijving';
$lang['t_author']              = 'Auteur';
$lang['t_sortauthor']          = 'Sorteer op auteur';
$lang['t_type']                = 'Type';
$lang['t_sorttype']            = 'Sorteer op type';
$lang['t_date']                = 'Laatste Update';
$lang['t_sortdate']            = 'Sorteer op datum';
$lang['t_popularity']          = 'Populariteit';
$lang['t_sortpopularity']      = 'Sorteer op populariteit';
$lang['t_compatible']          = 'Laatste Compatibel';
$lang['t_sortcompatible']      = 'Sorteer op compatibiliteit';
$lang['t_screenshot']          = 'Schermafdruk';
$lang['t_download']            = 'Download';
$lang['t_provides']            = 'Levert';
$lang['t_tags']                = 'Labels';
$lang['t_bundled']             = 'gebundeld';
$lang['screenshot_title']      = 'Schermafdruk van %s';
