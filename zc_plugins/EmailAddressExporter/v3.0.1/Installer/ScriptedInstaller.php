<?php
/**
 * Email Address Exporter - installer.
 *
 * Deliberately does NOT modify the shared query_builder rows any more.
 *
 * Earlier releases tried to rewrite the 'All Customers' and 'All Newsletter
 * Subscribers' queries so the export could include company and telephone. That
 * code never actually ran: it compared the stored query with `===` against an
 * UPPERCASE "SELECT ... FROM ... ORDER BY" string, while zc_install seeds those
 * rows in lowercase, so the match always failed and those columns always came
 * out empty. Rather than repair a plugin that silently edits core data shared
 * with the newsletter and gift-certificate modules (and never undoes it on
 * uninstall), this version builds its own query and reads those fields directly.
 *
 * @copyright Copyright 2003-2024 Zen Cart Development Team
 * @copyright Portions Copyright 2026 My Zen Cart Host (dbltoe)
 *
 * @author DrByte - original author
 * @author Swifty8078
 * @author Ooba_Scott
 * @author That Software Guy (swguy)
 * @author My Zen Cart Host (dbltoe) - v3.0.0
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall(): void
    {
        // De-register first: earlier releases registered this same page key, so
        // clearing it keeps installs and upgrades idempotent.
        zen_deregister_admin_pages(['emailExport']);
        zen_register_admin_page(
            'emailExport',
            'BOX_TOOLS_EMAIL_EXPORT',
            'FILENAME_EMAIL_EXPORT',
            '',
            'tools',
            'Y',
            10
        );
    }

    protected function executeUninstall(): void
    {
        zen_deregister_admin_pages(['emailExport']);
    }
}
