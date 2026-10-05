<?php
/**
 * @package Email Address Exporter
 * @subpackage plugins
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @copyright Portions Copyright 2026 My Zen Cart Host (dbltoe)
 *
 * @author DrByte - original author
 * @author Swifty8078
 * @author Ooba_Scott
 * @author That Software Guy (swguy)
 * @author My Zen Cart Host (dbltoe) - v3.0.0
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
// -----
// Read Me / GitHub buttons shown in the Plugin Manager's info box, matching the pattern
// established for Social Contact Footer (f:/Social_Contact_Footer) and Admin Add Customer.
// The Read Me URL is derived from this file's own on-disk location (rather than a hardcoded
// version string) so it can't go stale on a future version bump. Zen Cart's shipped
// zc_plugins/.htaccess denies everything then explicitly re-allows .html, so readme.html
// is reachable by design.
//
$eaePluginRelativeDir = basename(dirname(__DIR__)) . '/' . basename(__DIR__);
$eaeReadmeUrl = (defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/') . 'zc_plugins/' . $eaePluginRelativeDir . '/readme.html';
$eaeGithubUrl = 'https://github.com/dbltoe/email-address-exporter';

// -----
// The Zen Cart Plugins Library listing for this plugin, live once the submission is
// approved. The id in that URL is the same number as 'pluginId' below -- that is not a
// coincidence and the two must not drift apart: PluginManager stores pluginId as
// plugin_control.zc_contrib_id, and getPluginsAfterCheckingForNewVersionsOnline() posts
// those ids to zen-cart.com to find out whether a newer release exists. A wrong id means
// store owners are never told about updates.
//
$eaeDownloadUrl = 'https://www.zen-cart.com/downloads.php?do=file&id=6';

// -----
// The GitHub releases page for this tagged release.
//
// Note that the 'changelog' and 'github_repo' manifest keys below are NOT read by any
// supported Zen Cart version (v1.5.8 through v3.0.0) -- only pluginName,
// pluginDescription, pluginAuthor, pluginVersion, zcVersions and pluginId are consumed.
// They are recorded for the plugin library and for future core use. So this URL is also
// surfaced as a "Release Notes" link in the description below, which is the only field
// that actually reaches a store owner in the Plugin Manager panel.
//
$eaeReleasesUrl = 'https://github.com/dbltoe/email-address-exporter/releases/tag/v3.0.1';

// -----
// The Zen Cart forum's support thread for this plugin.
//
// PUT THE URL HERE BEFORE THE FIRST RELEASE, not after.
//
// On v2.2 and later a later edit is picked up on the next Plugin Manager scan. On v1.5.8,
// v2.0 and v2.1 it is not: those releases write plugin_control.description only on the
// INSERT that first creates the row, so whatever is here the first time a store scans the
// plugin is what that store shows for good. Adding the link afterwards reaches nobody on
// half the supported range short of an uninstall and re-install.
//
// An empty string renders nothing at all, which was the right outcome until the thread
// existed: a "Forum Support Thread" link that 404s is worse than no link, because somebody
// with a problem follows it.
//
$eaeForumUrl = 'https://www.zen-cart.com/showthread.php/231172-Email-Address-Exporter';

$eaeButtonGap = '6px';
$eaeLinks =
    '<div style="margin:10px 0 0;padding:0 0 0 ' . $eaeButtonGap . '">'
    . '<a href="' . $eaeReadmeUrl . '" target="_blank" rel="noopener noreferrer"'
    . ' class="btn btn-primary" role="button"'
    . ' style="margin:0 ' . $eaeButtonGap . ' 0 0">Read Me</a>'
    . '<a href="' . $eaeGithubUrl . '" target="_blank" rel="noopener noreferrer"'
    . ' class="btn btn-primary" role="button"'
    . ' style="margin:0 ' . $eaeButtonGap . ' 0 0">GitHub</a>'
    // The support thread, a third button (every free dbltoe plugin has one, 2026-10-05).
    // Renders nothing while $eaeForumUrl is empty.
    . ($eaeForumUrl !== ''
        ? '<a href="' . $eaeForumUrl . '" target="_blank" rel="noopener noreferrer"'
          . ' class="btn btn-primary" role="button"'
          . ' style="margin:0 ' . $eaeButtonGap . ' 0 0">Forum Support Thread</a>'
        : '')
    . '</div>';

// Release notes, as a plain link: it is reference material, not something the owner
// does to the plugin.
$eaeReleasesLink = '';
if ($eaeReleasesUrl !== '') {
    $eaeReleasesLink =
        '<div style="margin:4px 0 0;padding:0 0 0 ' . $eaeButtonGap . '">'
        . '<a href="' . $eaeReleasesUrl . '" target="_blank" rel="noopener noreferrer">'
        . 'Release Notes</a>'
        . '</div>';
}

$eaeCredits =
    '<div style="margin:8px 0 0;padding:0 0 0 ' . $eaeButtonGap . '"><em>'
    . 'Originally created by DrByte, with revisions by Swifty8078, Ooba_Scott '
    . 'and That Software Guy (swguy).'
    . '</em></div>';

return [
    'pluginVersion' => 'v3.0.1',
    'pluginName' => 'Export Email Addresses',
    'pluginDescription' =>
        'Export customer email addresses and contact details to CSV, TXT, XML, JSON or HTML. '
        . 'Build the list from the filters on the page - location, registration date, and what '
        . 'the customer has bought - or from a saved audience in Query Builder, and choose '
        . 'exactly which columns land in the file.'
        . $eaeLinks
        . $eaeReleasesLink
        . $eaeCredits,
    // Shown as the Author in Plugin Manager, and stored in
    // plugin_control.author / plugin_control_versions.author (varchar(64)).
    'pluginAuthor' => 'My Zen Cart Host (dbltoe)',
    // Stored as plugin_control.zc_contrib_id and sent to zen-cart.com to check for newer
    // releases; it must match the id in $eaeDownloadUrl above.
    'pluginId' => 6,
    // Recorded as metadata by the Plugin Manager; it does not gate installation.
    'zcVersions' => ['v158', 'v200', 'v201', 'v210', 'v220', 'v221', 'v222', 'v230', 'v300'],
    // Not read by any supported cart version; see the note above $eaeReleasesUrl.
    'changelog' => ($eaeReleasesUrl !== '') ? $eaeReleasesUrl : 'CHANGELOG.md',
    'github_repo' => $eaeGithubUrl,
    'pluginGroups' => [],
];
