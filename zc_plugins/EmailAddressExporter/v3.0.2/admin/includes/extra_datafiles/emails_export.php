<?php
/**
 * Defines the admin page filename constant.
 *
 * Zen Cart v3.0.0 also supports a plugin-root filenames.php for this, but both
 * v1.5.8 and v3.0.0 load admin/includes/extra_datafiles from installed plugins
 * (see admin/includes/application_bootstrap.php), so this location works on the
 * whole supported range from one file.
 *
 * @copyright Copyright 2003-2024 Zen Cart Development Team
 * @copyright Portions Copyright 2026 My Zen Cart Host (dbltoe)
 *
 * @author DrByte - original author
 * @author Swifty8078
 * @author Ooba_Scott
 * @author That Software Guy (swguy)
 * @author My Zen Cart Host (dbltoe) - v3.0.0
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */

defined('FILENAME_EMAIL_EXPORT') || define('FILENAME_EMAIL_EXPORT', 'emails_export');
