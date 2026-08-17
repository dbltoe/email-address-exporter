<?php
/**
 * Email Address Exporter - admin page.
 *
 * Two mutually-exclusive selection modes:
 *   'audience' - a saved query from the query_builder table (legacy behaviour).
 *   'filters'  - a query this plugin builds from the filter controls below.
 *
 * Target: PHP 7.4+ and Zen Cart v1.5.8 -> v3.0.0 from a single codebase.
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
 *
 * @var messageStack $messageStack
 * @var queryFactory $db
 */

require 'includes/application_top.php';

require_once __DIR__ . '/includes/emails_export_functions.php';

$formats = zcEAE_formats();
$availableColumns = zcEAE_available_columns();

$action = $_GET['action'] ?? '';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

// ---------------------------------------------------------------------------
// Gather and normalise input. Every value is re-displayed on the form, so this
// runs for both the initial GET and the export POST.
// ---------------------------------------------------------------------------
$mode = ($_POST['mode'] ?? 'filters') === 'audience' ? 'audience' : 'filters';

$format = strtolower((string)($_POST['format'] ?? 'csv'));
if (!isset($formats[$format])) {
    $format = 'csv';
}

$includeHeaders = !$isPost || !empty($_POST['include_headers']);

// 'digits' strips punctuation from telephone/fax values on the way out; see
// zcEAE_format_phone(). Stored data is never altered either way.
$phoneFormat = ($_POST['phone_format'] ?? 'raw') === 'digits' ? 'digits' : 'raw';

// Free-text values arrive HTML-encoded by the admin request sanitizer on every
// supported cart version -- see zcEAE_decode_input().
$audienceSelected = $_POST['audience_selected'] ?? '';
if (is_array($audienceSelected)) {
    $audienceSelected = $audienceSelected['text'] ?? '';
}
$audienceSelected = zcEAE_decode_input($audienceSelected);

$selectedColumns = zcEAE_sanitize_columns($isPost ? ($_POST['columns'] ?? []) : zcEAE_default_columns());

$filters = [
    // Default both consent guards ON for a fresh form; respect the posted
    // values (including "unchecked") once the admin has actually submitted.
    'newsletter_only'  => $isPost ? !empty($_POST['newsletter_only'])  : true,
    'exclude_no_email' => $isPost ? !empty($_POST['exclude_no_email']) : true,
    'country_id'       => (int)($_POST['country_id'] ?? 0),
    'zone_id'          => (int)($_POST['zone_id'] ?? 0),
    'state'            => trim(zcEAE_decode_input($_POST['state'] ?? '')),
    'city'             => trim(zcEAE_decode_input($_POST['city'] ?? '')),
    'registered_from'  => zcEAE_clean_date($_POST['registered_from'] ?? ''),
    'registered_to'    => zcEAE_clean_date($_POST['registered_to'] ?? ''),
    'ordered_from'     => zcEAE_clean_date($_POST['ordered_from'] ?? ''),
    'ordered_to'       => zcEAE_clean_date($_POST['ordered_to'] ?? ''),
    'orders_status'    => (int)($_POST['orders_status'] ?? 0),
    'products_id'      => (int)($_POST['products_id'] ?? 0),
    'categories_ids'   => [],
    'include_subcats'  => $isPost ? !empty($_POST['include_subcats']) : true,
    'has_ordered'      => !empty($_POST['has_ordered']),
    'never_ordered'    => !empty($_POST['never_ordered']),
];

$categoryId = (int)($_POST['categories_id'] ?? 0);
if ($categoryId > 0) {
    $filters['categories_ids'] = $filters['include_subcats']
        ? zcEAE_category_with_children($categoryId)
        : [$categoryId];
}

$filename = zcEAE_decode_input($_POST['filename'] ?? 'email_addresses');

// ---------------------------------------------------------------------------
// Export
// ---------------------------------------------------------------------------
// The Go button carries name="do_export", so a plain form.submit() -- which the
// country selector uses to repopulate the zone list -- re-renders the page
// instead of running an export.
if ($action === 'save' && $isPost && !empty($_POST['do_export'])) {
    $querySql = '';
    $exportColumns = $selectedColumns;

    if ($mode === 'audience') {
        if ($audienceSelected === '') {
            $messageStack->add(ERROR_PLEASE_SELECT_AUDIENCE, 'error');
        } else {
            // Read the saved query directly rather than through
            // get_audience_sql_query(), for the same reason the dropdown below
            // is built by hand: that helper matches on query_category LIKE
            // '%email%', so it never sees an audience filed under 'newsletters'
            // alone -- and instead of saying so, it falls through to treating
            // the name as a customer's email address and returns a query that
            // quietly matches nobody. Reading the row keeps this in step with
            // the list the admin actually chose from, and makes
            // ERROR_NO_SUCH_QUERY reachable rather than dead code.
            // Parameterised with bindVars() rather than concatenated, matching
            // what core does for its own name lookups (see
            // zen_register_admin_page()). 'stringIgnoreNull' escapes and adds
            // its own quotes -- hence none around the placeholder -- and is the
            // right rule over plain 'string', which maps any value containing
            // "NULL" to an unquoted null and would silently fail to match an
            // audience named, say, "NULL addresses".
            $sql = "SELECT query_string FROM " . TABLE_QUERY_BUILDER .
                   " WHERE query_name = :queryName:" .
                   " AND (query_category LIKE '%email%' OR query_category LIKE '%newsletters%')" .
                   " LIMIT 1";
            $sql = $db->bindVars($sql, ':queryName:', $audienceSelected, 'stringIgnoreNull');
            $saved = $db->Execute($sql);
            if ($saved->EOF) {
                $messageStack->add(ERROR_NO_SUCH_QUERY, 'error');
            } else {
                // parsed_query_string() expands the TABLE_* and $SESSION:
                // placeholders Query Builder stores. It lives in
                // includes/functions/audience.php, which is autoloaded for every
                // admin page at breakpoint 160 on v1.5.8 through v3.0.0 alike.
                $querySql = parsed_query_string($saved->fields['query_string']);
                // A saved query defines its own column list, so take whatever
                // it returns rather than the checkbox selection.
                $exportColumns = [];
            }
        }
    } else {
        if (empty($exportColumns)) {
            $messageStack->add(ERROR_NO_COLUMNS_SELECTED, 'error');
        } elseif ($filters['has_ordered'] && $filters['never_ordered']) {
            $messageStack->add(ERROR_CONFLICTING_ORDER_FILTERS, 'error');
        } else {
            $querySql = zcEAE_build_filter_query($filters, $exportColumns);
        }
    }

    if ($querySql !== '') {
        zen_set_time_limit(600);

        $result = $db->Execute($querySql);
        $records = $result->RecordCount();

        if ($records === 0) {
            $messageStack->add(ERROR_NO_RECORDS_FOUND, 'warning');
        } else {
            // Audience mode: derive headings from the first returned row.
            if (empty($exportColumns)) {
                $exportColumns = array_keys($result->fields);
            }

            try {
                $output = zcEAE_render($format, $result, $exportColumns, $includeHeaders, $phoneFormat);
            } catch (Throwable $e) {
                // Better a stated failure than a plausible-looking empty file.
                $messageStack->add_session(sprintf(ERROR_EXPORT_FAILED, $e->getMessage()), 'error');
                zen_redirect(zen_href_link(FILENAME_EMAIL_EXPORT));
                // zen_redirect() exits on its own, unless an observer answers
                // NOTIFY_ZEN_REDIRECT and it returns instead. Be explicit, so
                // that case cannot fall through to using an unset $output.
                exit;
            }

            // This page emits the store's customer contact list in bulk, which
            // is the most disclosure-sensitive thing the admin can do with it.
            // Record it where the rest of the admin's audit trail lives.
            zen_record_admin_activity(
                'Exported ' . (int)$records . ' customer record(s) as ' . strtoupper($format)
                . ' from ' . ($mode === 'audience' ? 'saved audience "' . $audienceSelected . '"' : 'custom filters')
                . '; columns: ' . implode(', ', $exportColumns),
                'warning'
            );

            if (!empty($formats[$format]['download'])) {
                $safeName = zcEAE_sanitize_filename($filename, $formats[$format]['ext']);
                $charset = defined('CHARSET') ? CHARSET : 'utf-8';

                // Excel on Windows assumes the system codepage for CSV unless a
                // UTF-8 BOM is present, which mangles accented names.
                if ($format === 'csv' && !empty($_POST['add_bom'])) {
                    $output = "\xEF\xBB\xBF" . $output;
                }

                header('Content-Type: ' . $formats[$format]['mime'] . '; charset=' . $charset);
                header('Content-Disposition: attachment; filename="' . $safeName . '"');
                // Deliberately no Content-Length: the admin may be running
                // output compression, which would make it disagree with the body.
                header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Expires: 0');

                echo $output;
                session_write_close();
                exit;
            }

            // HTML renders in the browser rather than downloading.
            $messageStack->add_session(sprintf(TEXT_RECORDS_PROCESSED, $records), 'success');
            echo $output;
            session_write_close();
            exit;
        }
    }
}

// ---------------------------------------------------------------------------
// Form data
// ---------------------------------------------------------------------------
$formatOptions = [];
foreach ($formats as $key => $info) {
    $formatOptions[] = ['id' => $key, 'text' => $info['label']];
}

$phoneFormatOptions = [
    ['id' => 'raw',    'text' => TEXT_PHONE_AS_STORED],
    ['id' => 'digits', 'text' => TEXT_PHONE_DIGITS],
];

// How many customers the active filters drop for want of the data they test,
// rather than for failing it. Only queried in filter mode, and only for filters
// actually in use, so a fresh page costs nothing.
$excluded = ['missing_registration_date' => 0, 'missing_address' => 0];
if ($mode === 'filters') {
    $excluded = zcEAE_count_excluded($filters, $db);
}

// Built directly rather than via get_audiences_list(), for two reasons:
//   1. That helper filters with LIKE '%category%', so no single argument
//      matches both the 'email' and 'email,newsletters' rows -- which is why
//      "All Customers" never used to appear on this page.
//   2. Called with 'email' it also appends every individual customer address
//      to the list, which is unusable on a store of any size.
// Uses a plugin-owned constant: core's TEXT_SELECT_AN_OPTION only gets loaded
// as a side effect of calling get_audiences_list(), which we no longer do.
$audienceOptions = [['id' => '', 'text' => TEXT_CHOOSE_AUDIENCE]];
$savedQueries = $db->Execute(
    "SELECT query_name FROM " . TABLE_QUERY_BUILDER .
    " WHERE query_category LIKE '%email%' OR query_category LIKE '%newsletters%'" .
    " ORDER BY query_name"
);
foreach ($savedQueries as $savedQuery) {
    $audienceOptions[] = ['id' => $savedQuery['query_name'], 'text' => $savedQuery['query_name']];
}

$countryOptions = [['id' => '0', 'text' => TEXT_ANY_COUNTRY]];
$countries = $db->Execute("SELECT countries_id, countries_name FROM " . TABLE_COUNTRIES . " ORDER BY countries_name");
foreach ($countries as $country) {
    $countryOptions[] = ['id' => $country['countries_id'], 'text' => $country['countries_name']];
}

$zoneOptions = [['id' => '0', 'text' => TEXT_ANY_STATE]];
if ($filters['country_id'] > 0) {
    $zones = $db->Execute(
        "SELECT zone_id, zone_name FROM " . TABLE_ZONES .
        " WHERE zone_country_id = " . (int)$filters['country_id'] . " ORDER BY zone_name"
    );
    foreach ($zones as $zone) {
        $zoneOptions[] = ['id' => $zone['zone_id'], 'text' => $zone['zone_name']];
    }
}

// zen_get_category_tree() leads with its own "Top" entry, whose id is
// TOPMOST_CATEGORY_PARENT_ID -- that is, 0, the same value as "-- any category
// --". Left in, the dropdown carries two options with value 0, and picking
// "Top" silently means "no category filter at all".
$categoryOptions = [['id' => '0', 'text' => TEXT_ANY_CATEGORY]];
foreach (zen_get_category_tree() as $categoryEntry) {
    if ((int)$categoryEntry['id'] > 0) {
        $categoryOptions[] = $categoryEntry;
    }
}

$statusOptions = [['id' => '0', 'text' => TEXT_ANY_ORDER_STATUS]];
$statuses = $db->Execute(
    "SELECT orders_status_id, orders_status_name FROM " . TABLE_ORDERS_STATUS .
    " WHERE language_id = " . (int)$_SESSION['languages_id'] . " ORDER BY orders_status_name"
);
foreach ($statuses as $status) {
    $statusOptions[] = ['id' => $status['orders_status_id'], 'text' => $status['orders_status_name']];
}

$charset = defined('CHARSET') ? CHARSET : 'utf-8';
?>
<!doctype html>
<html <?= HTML_PARAMS ?>>
<head>
    <?php
    // admin_html_head.php auto-loads a plugin stylesheet named after the page
    // (admin/includes/css/emails_export.css) on both v1.5.8 and v3.0.0, so it
    // must NOT be linked manually -- a relative href would resolve against
    // /admin/ and 404.
    require DIR_WS_INCLUDES . 'admin_html_head.php';
    ?>
</head>
<body>
<!-- header //-->
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<!-- header_eof //-->
<div class="container-fluid">
    <!-- body //-->
    <?php // Classed so the stylesheet can restate its size in rem; see emails_export.css. ?>
    <h1 class="eae-heading"><?= HEADING_TITLE ?></h1>
    <!-- body_text //-->
    <div class="row">
        <div class="col-xs-12 eae-instructions eae-measure"><?= TEXT_INSTRUCTIONS ?></div>
    </div>

    <?php
    // Vertical (stacked) form, not form-horizontal: inside a two-column layout a
    // 3/9 label-beside-control split produces very long horizontal runs on a wide
    // monitor. Labels sit above their controls instead, and .eae-measure caps
    // prose width so no sentence runs the full width of the screen.
    ?>
    <?= zen_draw_form('export', FILENAME_EMAIL_EXPORT, 'action=save', 'post', 'id="eaeForm"') ?>

    <div class="row eae-panels">

        <?php // LEFT: who to export. col-md-* => side by side at 992px and up
              // (desktop, tablet landscape); stacked below (tablet portrait, mobile). ?>
        <div class="col-xs-12 col-md-7 col-lg-8">
            <div class="eae-panel">
                <h2><?= TEXT_LEGEND_SOURCE ?></h2>

                <div class="form-group">
                    <div class="radio">
                        <label>
                            <input type="radio" name="mode" value="filters" id="modeFilters" <?= $mode === 'filters' ? 'checked' : '' ?>>
                            <?= TEXT_MODE_FILTERS ?>
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="mode" value="audience" id="modeAudience" <?= $mode === 'audience' ? 'checked' : '' ?>>
                            <?= TEXT_MODE_AUDIENCE ?>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="audienceRow">
                    <?= zen_draw_label(TEXT_PLEASE_SELECT_AUDIENCE, 'audience_selected', 'class="control-label"') ?>
                    <?= zen_draw_pull_down_menu('audience_selected', $audienceOptions, $audienceSelected, 'class="form-control" id="audience_selected"') ?>
                    <p class="help-block eae-measure"><?= TEXT_AUDIENCE_HELP ?></p>
                </div>

                <div id="filterFields">
                    <div class="form-group">
                        <?php // No <strong> here: Bootstrap renders checkbox labels at normal weight, and bold is reserved for Title Case labels. ?>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="newsletter_only" value="1" <?= $filters['newsletter_only'] ? 'checked' : '' ?>>
                                <?= TEXT_NEWSLETTER_ONLY ?>
                            </label>
                        </div>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="exclude_no_email" value="1" <?= $filters['exclude_no_email'] ? 'checked' : '' ?>>
                                <?= TEXT_EXCLUDE_NO_EMAIL ?>
                            </label>
                        </div>
                        <p class="help-block eae-measure"><?= TEXT_CONSENT_WARNING ?></p>
                        <p class="help-block eae-measure"><?= TEXT_FILTERS_INTRO ?></p>
                    </div>

                    <h3><?= TEXT_LEGEND_LOCATION ?></h3>

                    <div class="row">
                        <div class="col-xs-12 col-sm-6 col-lg-4">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_COUNTRY, 'country_id', 'class="control-label"') ?>
                                <?= zen_draw_pull_down_menu('country_id', $countryOptions, (string)$filters['country_id'], 'class="form-control" id="country_id" onchange="this.form.submit();"') ?>
                                <p class="help-block eae-measure"><?= TEXT_COUNTRY_HELP ?></p>
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-6 col-lg-4">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_STATE, 'zone_id', 'class="control-label"') ?>
                                <?php if (count($zoneOptions) > 1) { ?>
                                    <?= zen_draw_pull_down_menu('zone_id', $zoneOptions, (string)$filters['zone_id'], 'class="form-control" id="zone_id"') ?>
                                <?php } else { ?>
                                    <?php // zen_draw_input_field() runs the value through zen_output_string(), so it must NOT be pre-escaped. ?>
                                    <?= zen_draw_input_field('state', $filters['state'], 'class="form-control" id="state" placeholder="' . htmlspecialchars(TEXT_STATE_PLACEHOLDER, ENT_COMPAT, $charset) . '"') ?>
                                <?php } ?>
                                <p class="help-block eae-measure"><?= TEXT_STATE_HELP ?></p>
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-6 col-lg-4">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_CITY, 'city', 'class="control-label"') ?>
                                <?= zen_draw_input_field('city', $filters['city'], 'class="form-control" id="city"') ?>
                                <p class="help-block eae-measure"><?= TEXT_CITY_HELP ?></p>
                            </div>
                        </div>
                        <?php if ($excluded['missing_address'] > 0) { ?>
                            <div class="col-xs-12">
                                <div class="alert alert-warning eae-measure eae-excluded">
                                    <?= sprintf(TEXT_EXCLUDED_NO_ADDRESS, (int)$excluded['missing_address']) ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <h3><?= TEXT_LEGEND_REGISTERED ?></h3>

                    <div class="row">
                        <div class="col-xs-6 col-sm-4 col-lg-3">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_REGISTERED_FROM, 'registered_from', 'class="control-label"') ?>
                                <?= zen_draw_input_field('registered_from', $filters['registered_from'], 'class="form-control" id="registered_from"', false, 'date') ?>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-4 col-lg-3">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_REGISTERED_TO, 'registered_to', 'class="control-label"') ?>
                                <?= zen_draw_input_field('registered_to', $filters['registered_to'], 'class="form-control" id="registered_to"', false, 'date') ?>
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <p class="help-block eae-measure"><?= TEXT_DATE_HELP ?></p>
                            <p class="help-block eae-measure"><?= TEXT_REGISTERED_DATE_NOTE ?></p>
                            <?php if ($excluded['missing_registration_date'] > 0) { ?>
                                <div class="alert alert-warning eae-measure eae-excluded">
                                    <?= sprintf(TEXT_EXCLUDED_NO_REG_DATE, (int)$excluded['missing_registration_date']) ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <h3><?= TEXT_LEGEND_PURCHASES ?></h3>

                    <div class="form-group">
                        <p class="help-block eae-measure"><?= TEXT_PURCHASE_HELP ?></p>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="has_ordered" value="1" <?= $filters['has_ordered'] ? 'checked' : '' ?>>
                                <?= TEXT_HAS_ORDERED ?>
                            </label>
                        </div>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="never_ordered" value="1" <?= $filters['never_ordered'] ? 'checked' : '' ?>>
                                <?= TEXT_NEVER_ORDERED ?>
                            </label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-6 col-sm-4 col-lg-3">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_ORDERED_FROM, 'ordered_from', 'class="control-label"') ?>
                                <?= zen_draw_input_field('ordered_from', $filters['ordered_from'], 'class="form-control" id="ordered_from"', false, 'date') ?>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-4 col-lg-3">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_ORDERED_TO, 'ordered_to', 'class="control-label"') ?>
                                <?= zen_draw_input_field('ordered_to', $filters['ordered_to'], 'class="form-control" id="ordered_to"', false, 'date') ?>
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <p class="help-block eae-measure"><?= TEXT_DATE_HELP ?></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-12 col-sm-6 col-lg-4">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_ORDER_STATUS, 'orders_status', 'class="control-label"') ?>
                                <?= zen_draw_pull_down_menu('orders_status', $statusOptions, (string)$filters['orders_status'], 'class="form-control" id="orders_status"') ?>
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-6 col-lg-4">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_CATEGORY, 'categories_id', 'class="control-label"') ?>
                                <?= zen_draw_pull_down_menu('categories_id', $categoryOptions, (string)$categoryId, 'class="form-control" id="categories_id"') ?>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="include_subcats" value="1" <?= $filters['include_subcats'] ? 'checked' : '' ?>>
                                        <?= TEXT_INCLUDE_SUBCATEGORIES ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-6 col-lg-4">
                            <div class="form-group">
                                <?= zen_draw_label(TEXT_FILTER_PRODUCT_ID, 'products_id', 'class="control-label"') ?>
                                <?= zen_draw_input_field('products_id', $filters['products_id'] > 0 ? (string)$filters['products_id'] : '', 'class="form-control" id="products_id"') ?>
                                <p class="help-block eae-measure"><?= TEXT_PRODUCT_ID_HELP ?></p>
                            </div>
                        </div>
                    </div>
                </div><!-- /#filterFields -->
            </div>
        </div>

        <?php // RIGHT: what the exported file contains. ?>
        <div class="col-xs-12 col-md-5 col-lg-4">
            <div class="eae-panel" id="columnsBlock">
                <h2><?= TEXT_LEGEND_COLUMNS ?></h2>
                <p class="help-block eae-measure"><?= TEXT_COLUMNS_HELP ?></p>
                <div class="row eae-columns">
                    <?php foreach ($availableColumns as $key => $info) { ?>
                        <div class="col-xs-12 col-sm-6 col-md-12 col-lg-6">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="columns[]" value="<?= htmlspecialchars($key, ENT_COMPAT, $charset) ?>"
                                        <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>
                                        <?= !empty($info['always']) ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($info['label'], ENT_COMPAT, $charset) ?>
                                    <?php if (!empty($info['always'])) { ?>
                                        <input type="hidden" name="columns[]" value="<?= htmlspecialchars($key, ENT_COMPAT, $charset) ?>">
                                    <?php } ?>
                                </label>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="eae-panel">
                <h2><?= TEXT_LEGEND_OUTPUT ?></h2>

                <div class="form-group">
                    <?= zen_draw_label(TEXT_EMAIL_EXPORT_FORMAT, 'format', 'class="control-label"') ?>
                    <?= zen_draw_pull_down_menu('format', $formatOptions, $format, 'class="form-control" id="format"') ?>
                </div>

                <div class="form-group">
                    <?= zen_draw_label(TEXT_PHONE_FORMAT, 'phone_format', 'class="control-label"') ?>
                    <?= zen_draw_pull_down_menu('phone_format', $phoneFormatOptions, $phoneFormat, 'class="form-control" id="phone_format"') ?>
                    <p class="help-block eae-measure"><?= TEXT_PHONE_HELP ?></p>
                </div>

                <div class="form-group">
                    <?= zen_draw_label(TEXT_EMAIL_EXPORT_FILENAME, 'filename', 'class="control-label"') ?>
                    <?= zen_draw_input_field('filename', (string)$filename, 'class="form-control" id="filename"') ?>
                    <p class="help-block eae-measure"><?= TEXT_FILENAME_HELP ?></p>
                </div>

                <div class="form-group">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="include_headers" value="1" <?= $includeHeaders ? 'checked' : '' ?>>
                            <?= TEXT_INCLUDE_HEADERS ?>
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="add_bom" value="1" <?= (!$isPost || !empty($_POST['add_bom'])) ? 'checked' : '' ?>>
                            <?= TEXT_ADD_BOM ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.eae-panels -->

    <div class="row">
        <div class="col-xs-12 text-right eae-actions">
            <?php // Lets a no-JS admin repopulate the state/zone list without exporting. ?>
            <button type="submit" name="refresh" value="1" class="btn btn-default"><?= TEXT_REFRESH_OPTIONS ?></button>
            <button type="submit" name="do_export" value="1" class="btn btn-primary"><?= IMAGE_GO ?></button>
            <a href="<?= zen_href_link(FILENAME_DEFAULT) ?>" class="btn btn-default" role="button"><?= IMAGE_CANCEL ?></a>
        </div>
    </div>

    </form>
    <!-- body_text_eof //-->
    <!-- body_eof //-->
</div>
<!-- footer //-->
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
<!-- footer_eof //-->
<br>
<script>
    (function () {
        var audienceRadio = document.getElementById('modeAudience');
        var filtersRadio  = document.getElementById('modeFilters');
        var audienceRow   = document.getElementById('audienceRow');
        var filterFields  = document.getElementById('filterFields');
        var columnsBlock  = document.getElementById('columnsBlock');

        // Filters and the column picker now sit in different grid columns, so each
        // is toggled on its own. Output stays visible in both modes.
        function syncMode() {
            var useAudience = audienceRadio.checked;
            audienceRow.style.display  = useAudience ? '' : 'none';
            filterFields.style.display = useAudience ? 'none' : '';
            columnsBlock.style.display = useAudience ? 'none' : '';
        }

        audienceRadio.addEventListener('change', syncMode);
        filtersRadio.addEventListener('change', syncMode);
        syncMode();
    })();
</script>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
