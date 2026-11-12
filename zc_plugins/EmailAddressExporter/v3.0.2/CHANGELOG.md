# Changelog

All notable changes to the Email Address Exporter plugin.

## v3.0.2

Released 2026-11-11.

- **License: GNU General Public License, version 3** (was version 2).
- **readme.html is replaced by README.md.** The ReadMe button in Plugin
  Manager opens it inside the admin, for superusers only, in its own window
  (`index.php?cmd=email_address_exporter_readme`); it is no longer a public
  file under `zc_plugins/`. The same README.md is at the root of the download.
- Nothing changes on the export page or in the files it produces.

Upgrading: uninstall the earlier release in Admin > Modules > Plugin Manager,
then install v3.0.2. No data is affected; this plugin stores nothing of its
own.

## v3.0.1

Hardening only. No functional change, and nothing to reconfigure — upgrading is
worthwhile but not urgent.

### Fixed

- **The admin page now refuses to run unless the admin has been bootstrapped.**
  `admin/emails_export.php` gained the `IS_ADMIN_FLAG` guard that the bundled
  DisplayLogs plugin uses.

  On the normal path this costs nothing: the page is reached as
  `admin/index.php?cmd=emails_export`, and `index.php` loads
  `admin/includes/application_bootstrap.php` — which defines `IS_ADMIN_FLAG` —
  before routing there, on v1.5.8, v2.0.0, v2.2.2 and v3.0.0 alike.

  It matters for a direct request. `zc_plugins/` is reachable over the web on any
  host that does not honour the shipped `zc_plugins/.htaccess`: nginx reads no
  `.htaccess` at all, and Apache ignores it under `AllowOverride None`. Without
  the guard, such a request fell into the page's relative
  `require 'includes/application_top.php'`, failed to resolve it, and emitted a
  fatal error disclosing the filesystem path. No code execution and no data
  exposure, but no reason to leak the path either.

  Note the guard rule differs by location: a classic page in `/admin/` must
  **not** carry it, because such a page can be requested directly and
  self-bootstraps through its own `require application_top.php`. Core's own
  admin pages have no guard for exactly that reason.

### Added

- Unit tests for the pure helper functions, discovered by Zen Cart v3.0.0's
  plugin test runner at `tests/Unit/`. Run with
  `composer tests-plugin -- --plugin EmailAddressExporter`. They are in the
  repository but deliberately not in the distributed package.

## v3.0.0

Compatible with Zen Cart v1.5.8 through v3.0.0, on PHP 7.4 through 8.x, from a
single codebase.

### Fixed

- **Choosing CSV silently exported a TXT file.** The format was read with
  `!empty($_POST['format'])`, and CSV was option id `0`, so it always fell
  through to the default. Formats are now keyed by name rather than by index.
- **The company and telephone columns were always empty.** The installer tried
  to rewrite the `query_builder` rows to join `address_book`, but compared the
  stored SQL with `===` against an UPPERCASE `SELECT ... FROM ... ORDER BY`
  string while Zen Cart seeds those rows in lowercase. The comparison never
  matched, so the rewrite never ran and the extra fields never existed. The
  plugin now selects those fields directly instead of depending on a mutation
  of shared core data.
- **The "All Customers" audience never appeared.** The page asked for audiences
  in the `newsletters` category, but Zen Cart seeds that row under `email`
  only. The audience list is now read directly and covers both categories.
- **A spurious error on every page load.** Opening the page immediately showed
  "Please select an audience list to export", because the check ran before
  anything had been submitted. It now only validates on submit.
- **XML output was not well-formed.** An `<address_book>` element was opened for
  every record but closed only once at the end, and values were never escaped,
  so any `&` or `<` in the data broke the document. Output is now a single root
  with one `<contact>` per customer and all values escaped.
- **CSV values were not quoted or escaped**, so a comma or quote inside a field
  (for example `O'Hara, Jr.`) corrupted the row. Now RFC 4180 compliant.
- **The export filename was used verbatim in the `Content-Disposition` header**,
  with no protection against path separators or CRLF header injection.
- Removed the double HTML-escaping of re-displayed form values, which turned an
  apostrophe in a filename into `&#039;`.
- **Free-text filters containing `&`, `<`, `>` or `"` silently matched nothing.**
  Zen Cart's `AdminRequestSanitizer` runs `htmlspecialchars(..., ENT_COMPAT)`
  over every POST value that is not a registered core parameter &mdash; which is
  every field this plugin defines. A city typed as `Stoke & Trent` therefore
  arrived as `Stoke &amp; Trent` and was searched for literally. Such values are
  now decoded before use. This behaviour is identical on v1.5.8 through v3.0.0
  (`DO_STRICT_SANITIZATION` defaults to true on all of them), so it affected
  every supported cart.

### Added

- **Filtering**, as an alternative to the saved Query Builder audiences:
  - country, state/zone (dropdown when the country has zones, free text
    otherwise), and city;
  - registration date range;
  - purchase date range, order status, category (optionally including
    subcategories), and product ID;
  - has-ordered / has-never-ordered.
- **Selectable columns** &mdash; 16 available, covering name, contact details, the
  full default address, newsletter flag, email format and account dates.
  The email address is always included.
- **JSON export format.**
- **Consent filters**, both on by default: restrict to newsletter subscribers,
  and exclude customers whose email format is `NONE`.
- **Telephone formatting option.** Customer-entered numbers are rarely
  consistent, so "Digits only" strips brackets, spaces, dashes and dots on the
  way out. A leading `+` and any extension are preserved; stored data is never
  modified. Adding consistent formatting is deliberately not offered &mdash; it
  would require a dialling plan Zen Cart does not record.
- **UTF-8 BOM option for CSV**, so Excel on Windows renders accented characters
  correctly.
- **CSV injection protection** &mdash; values starting with `=`, `+`, `-` or `@` are
  prefixed with an apostrophe so spreadsheets treat them as text.
- Optional header row, for all formats rather than just CSV and HTML.
- A self-contained `readme.html`, linked from the Plugin Manager panel.

### Changed

- The installer no longer edits the shared `query_builder` table. That mutation
  never worked (see above), was never reverted on uninstall, and affected the
  newsletter and gift-certificate modules too. The plugin now reads the fields
  it needs directly.
- A customer matching several qualifying orders is returned once. Purchase
  conditions use an `EXISTS` test rather than a join.
- Dropped the obsolete Internet Explorer branch and its unreachable
  `Content-Type` handling; the correct MIME type is now sent for each format.
- Removed the unused `$save_to_file_checked` variable and other dead code.
- The file extension is derived from the chosen format rather than trusted from
  user input.
- Admin page markup reorganised into labelled sections.
- Every filter now states what happens when it is left alone, and the Filters
  section opens by saying that all of them are optional and combine with AND.
- **Silently excluded customers are now counted and reported.** Two filters can
  drop a customer for want of the data they test rather than for failing it,
  because comparing NULL to anything is never true:
  - a registration-date bound excludes anyone with no recorded registration
    date (typically admin-created or imported customers);
  - a location filter excludes anyone with no default address on file.

  The page now says how many, in place, next to the filter responsible &mdash; for
  example "12 customer(s) match everything else you have chosen but have no
  recorded registration date". The count honours every other active filter, so
  it is the number that would otherwise have been in the file. Nothing is
  queried unless the relevant filter is actually in use.
- The two-column layout: who to export on the left, what the file contains on
  the right, side by side from 992px and stacked below. Labels sit above their
  controls rather than beside them, and prose is capped at 72 characters a line
  so nothing runs the full width of a wide monitor.
- Bold text on the admin page is now consistently Title Case followed by a
  colon.
- **Nothing on the page renders smaller than 14px, and every font size is a
  relative unit** (WCAG 2.1 SC 1.4.4). Worth knowing if you restyle this page:
  the effective root is **11px**, not Bootstrap's 10px &mdash; the admin's own
  `stylesheet.css` loads after Bootstrap and sets
  `a, body, html, table { font: normal normal 11px Verdana }` at equal
  specificity, so it wins. That also makes the stock admin body text 11px, and
  the core heading scale h1 18 / h2 17 / h3 15 / h4 14 / h5 13 / h6 12 px. This
  page uses 1.3rem body (14.3px) rising to 1.6rem for the title (17.6px), which
  clears the floor while staying in step with the rest of the admin. Bootstrap's
  px-based form and helper classes are each restated so they cannot override it.
- Plugin Manager now shows Read Me and GitHub buttons matching the pattern used
  by Social Contact Footer and Admin Add Customer. The Read Me URL is derived
  from the manifest's own directory, so a version bump cannot leave it stale.

### Version coverage

Verified against four checkouts &mdash; v1.5.8a, v2.0.0, v2.2.2 and v3.0.0-dev &mdash;
rather than just the two endpoints, because v2.2.0 reworked several core areas.
Findings relevant to plugin authors:

- The admin **Plugin Manager was rewritten in v2.2.0** (`table_view.php` gives
  way to a dedicated `plugin_manager.php` template). `pluginDescription` is
  still concatenated into HTML unescaped by `PluginManagerController` on all
  four, so HTML links in it continue to render; v2.2.0+ additionally passes it
  through `zen_lookup_admin_menu_language_override()`, which is a pass-through
  when no override is defined.
- `orders_status` gained `orders_status_color_code` in v2.2.0. Additive only,
  and this plugin selects columns explicitly.
- `zen_register_admin_page()` gained parameter and return types; the behaviour
  is unchanged.
- `zen_config()`, `zen_add_filemtime()` and
  `zen_lookup_admin_menu_language_override()` do **not** exist on v1.5.8. Note
  that v3.0.0's `get_audiences_list()` calls `zen_config()` internally &mdash; another
  reason this plugin builds its own audience list.
- Plugin language files load from the same
  `<plugin>/admin/includes/languages/<lang>/` path on all four.

### Notes for developers

- Shared helpers live in `admin/includes/emails_export_functions.php` and are
  required by absolute path. They are deliberately **not** placed in
  `admin/includes/functions/extra_functions/`: Zen Cart v1.5.8 auto-loads that
  directory for plugins, but v3.0.0 does not, so relying on it would break
  silently on newer carts.
- The plugin ships no PSR-4 classes on purpose. Plugin class paths differ
  between the supported versions (`classes/admin` on v1.5.8 versus
  `admin/includes/classes/` on v3.0.0).
- `FILENAME_EMAIL_EXPORT` stays in `admin/includes/extra_datafiles/`, which both
  v1.5.8 and v3.0.0 load. v3.0.0 also supports a plugin-root `filenames.php`,
  but that would not work on v1.5.8.
