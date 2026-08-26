<?php
/**
 * Email Address Exporter - shared helpers.
 *
 * NOTE ON LOADING: this file is deliberately required by absolute path from
 * admin/emails_export.php rather than being dropped into
 * admin/includes/functions/extra_functions/. Zen Cart v1.5.8 auto-loads that
 * plugin directory from admin/includes/application_bootstrap.php, but v3.0.0
 * does NOT, so relying on it would silently break on the newer carts.
 *
 * Target: PHP 7.4+ and Zen Cart v1.5.8 -> v3.0.0 from a single codebase.
 * Keep this file free of PHP 8-only syntax (no match(), no named arguments,
 * no nullsafe operator, no constructor promotion, no enums).
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

if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    exit('Illegal Access');
}

/**
 * The export formats this plugin can emit.
 *
 * 'download' => true streams as a file attachment; false renders in-browser.
 */
function zcEAE_formats(): array
{
    return [
        'csv'  => ['label' => 'CSV',  'ext' => 'csv',  'mime' => 'text/csv',              'download' => true],
        'txt'  => ['label' => 'TXT',  'ext' => 'txt',  'mime' => 'text/plain',            'download' => true],
        'xml'  => ['label' => 'XML',  'ext' => 'xml',  'mime' => 'application/xml',       'download' => true],
        'json' => ['label' => 'JSON', 'ext' => 'json', 'mime' => 'application/json',      'download' => true],
        'html' => ['label' => 'HTML', 'ext' => 'html', 'mime' => 'text/html',             'download' => false],
    ];
}

/**
 * Columns offered in "custom filters" mode.
 *
 * 'select' is the SQL expression; the array key doubles as the output column
 * heading, so keys stay lowercase/underscored and safe for XML element names.
 */
function zcEAE_available_columns(): array
{
    return [
        'customers_email_address' => ['label' => 'Email address',   'select' => 'c.customers_email_address', 'always' => true],
        'customers_firstname'     => ['label' => 'First name',      'select' => 'c.customers_firstname'],
        'customers_lastname'      => ['label' => 'Last name',       'select' => 'c.customers_lastname'],
        'customers_telephone'     => ['label' => 'Telephone',       'select' => 'c.customers_telephone'],
        'customers_fax'           => ['label' => 'Fax',             'select' => 'c.customers_fax'],
        'company_name'            => ['label' => 'Company',         'select' => 'a.entry_company'],
        'street_address'          => ['label' => 'Street address',  'select' => 'a.entry_street_address'],
        'suburb'                  => ['label' => 'Suburb',          'select' => 'a.entry_suburb'],
        'city'                    => ['label' => 'City',            'select' => 'a.entry_city'],
        'state'                   => ['label' => 'State / Zone',    'select' => "COALESCE(NULLIF(a.entry_state, ''), z.zone_name, '')"],
        'postcode'                => ['label' => 'Postcode',        'select' => 'a.entry_postcode'],
        'country'                 => ['label' => 'Country',         'select' => "COALESCE(co.countries_name, '')"],
        'customers_newsletter'    => ['label' => 'Newsletter flag', 'select' => 'c.customers_newsletter'],
        'customers_email_format'  => ['label' => 'Email format',    'select' => 'c.customers_email_format'],
        'date_account_created'    => ['label' => 'Date registered', 'select' => 'ci.customers_info_date_account_created'],
        'date_of_last_logon'      => ['label' => 'Last logon',      'select' => 'ci.customers_info_date_of_last_logon'],
    ];
}

/**
 * Columns selected by default the first time the page is opened.
 */
function zcEAE_default_columns(): array
{
    return ['customers_email_address', 'customers_firstname', 'customers_lastname', 'company_name', 'customers_telephone'];
}

/**
 * Reduce a user-supplied column list to known keys, preserving registry order
 * and always keeping the email address column.
 */
function zcEAE_sanitize_columns($requested): array
{
    $available = zcEAE_available_columns();
    $requested = is_array($requested) ? $requested : [];

    $selected = [];
    foreach ($available as $key => $info) {
        if (!empty($info['always']) || in_array($key, $requested, true)) {
            $selected[] = $key;
        }
    }
    return $selected;
}

/**
 * Undo Zen Cart's admin request sanitizing for a free-text field.
 *
 * AdminRequestSanitizer::traverseStrictSanitize() runs
 * htmlspecialchars($value, ENT_COMPAT, CHARSET, true) over every POST/GET value
 * that is not a registered parameter -- which includes every field this plugin
 * invents. It is enabled by default (DO_STRICT_SANITIZATION) on v1.5.8 through
 * v3.0.0 alike.
 *
 * So a city typed as "Stoke & Trent" reaches us as "Stoke &amp; Trent", and
 * matching that against the database would silently return nothing. Decode
 * before using the value in a query. Double-encoding is on, so a value that
 * genuinely contained "&amp;" round-trips correctly through a single decode.
 */
function zcEAE_decode_input($value): string
{
    $charset = defined('CHARSET') ? CHARSET : 'UTF-8';
    return html_entity_decode((string)$value, ENT_QUOTES, $charset);
}

/**
 * Validate a YYYY-MM-DD string from a date input. Returns '' when unusable,
 * so callers can simply test for emptiness.
 */
function zcEAE_clean_date($value): string
{
    $value = trim((string)$value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return '';
    }
    $parts = explode('-', $value);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        return '';
    }
    return $value;
}

/**
 * Build the SELECT for "custom filters" mode.
 *
 * Purchase-history conditions are expressed as a single EXISTS subquery so the
 * customer row is never duplicated -- no GROUP BY, no DISTINCT needed.
 *
 * @param array $filters raw (already type-checked) filter values
 * @param array $columns sanitized column keys
 */
function zcEAE_build_filter_query(array $filters, array $columns): string
{
    $available = zcEAE_available_columns();

    $select = [];
    foreach ($columns as $key) {
        $select[] = $available[$key]['select'] . ' AS ' . $key;
    }

    return "SELECT " . implode(', ', $select)
        . zcEAE_filter_from()
        . zcEAE_filter_where($filters)
        . " ORDER BY c.customers_lastname, c.customers_firstname, c.customers_email_address";
}

/**
 * The FROM and JOIN clauses, shared by the export query and the
 * excluded-customer counts so the two can never drift apart.
 */
function zcEAE_filter_from(): string
{
    return "
              FROM " . TABLE_CUSTOMERS . " c
              LEFT JOIN " . TABLE_ADDRESS_BOOK . " a
                     ON a.customers_id = c.customers_id
                    AND a.address_book_id = c.customers_default_address_id
              LEFT JOIN " . TABLE_COUNTRIES . " co ON co.countries_id = a.entry_country_id
              LEFT JOIN " . TABLE_ZONES . " z      ON z.zone_id = a.entry_zone_id
              LEFT JOIN " . TABLE_CUSTOMERS_INFO . " ci ON ci.customers_info_id = c.customers_id";
}

/**
 * Build the WHERE clause.
 *
 * $probe turns the query into a count of customers silently excluded because
 * the data a filter tests is missing rather than because it failed the test.
 * Both cases arise from the LEFT JOINs above: comparing NULL to anything is
 * never true, so such a customer just disappears from the result.
 *
 *   ''                          - the real export query.
 *   'missing_registration_date' - drops the date bounds and finds customers
 *                                 with no recorded registration date, who
 *                                 cannot satisfy either bound.
 *   'missing_address'           - drops the location tests and finds customers
 *                                 with no default address row, who cannot
 *                                 satisfy any of them.
 *
 * Every other filter still applies, so the number reported is the number of
 * customers that would otherwise have been in the file.
 */
function zcEAE_filter_where(array $filters, string $probe = ''): string
{
    $sql = " WHERE c.customers_email_address <> ''";

    // --- Consent / deliverability -------------------------------------------
    if (!empty($filters['newsletter_only'])) {
        $sql .= " AND c.customers_newsletter = '1'";
    }
    if (!empty($filters['exclude_no_email'])) {
        $sql .= " AND c.customers_email_format <> 'NONE'";
    }

    // --- Geography -----------------------------------------------------------
    if ($probe === 'missing_address') {
        $sql .= " AND a.address_book_id IS NULL";
    } else {
        if (!empty($filters['country_id'])) {
            $sql .= " AND a.entry_country_id = " . (int)$filters['country_id'];
        }
        if (!empty($filters['zone_id'])) {
            $sql .= " AND a.entry_zone_id = " . (int)$filters['zone_id'];
        } elseif (!empty($filters['state'])) {
            $sql .= " AND (a.entry_state LIKE '%" . zen_db_input($filters['state']) . "%'"
                  . " OR z.zone_name LIKE '%" . zen_db_input($filters['state']) . "%')";
        }
        if (!empty($filters['city'])) {
            $sql .= " AND a.entry_city LIKE '%" . zen_db_input($filters['city']) . "%'";
        }
    }

    // --- Registration date ---------------------------------------------------
    if ($probe === 'missing_registration_date') {
        $sql .= " AND ci.customers_info_date_account_created IS NULL";
    } else {
        if (!empty($filters['registered_from'])) {
            $sql .= " AND ci.customers_info_date_account_created >= '" . zen_db_input($filters['registered_from']) . " 00:00:00'";
        }
        if (!empty($filters['registered_to'])) {
            $sql .= " AND ci.customers_info_date_account_created <= '" . zen_db_input($filters['registered_to']) . " 23:59:59'";
        }
    }

    // --- Purchase history ----------------------------------------------------
    $orderConditions = [];
    if (!empty($filters['ordered_from'])) {
        $orderConditions[] = "o.date_purchased >= '" . zen_db_input($filters['ordered_from']) . " 00:00:00'";
    }
    if (!empty($filters['ordered_to'])) {
        $orderConditions[] = "o.date_purchased <= '" . zen_db_input($filters['ordered_to']) . " 23:59:59'";
    }
    if (!empty($filters['orders_status'])) {
        $orderConditions[] = "o.orders_status = " . (int)$filters['orders_status'];
    }
    if (!empty($filters['products_id'])) {
        $orderConditions[] = "op.products_id = " . (int)$filters['products_id'];
    }
    if (!empty($filters['categories_ids']) && is_array($filters['categories_ids'])) {
        $catIds = [];
        foreach ($filters['categories_ids'] as $catId) {
            $catId = (int)$catId;
            if ($catId > 0) {
                $catIds[] = $catId;
            }
        }
        if (!empty($catIds)) {
            $orderConditions[] = "op.products_id IN (SELECT ptc.products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc"
                               . " WHERE ptc.categories_id IN (" . implode(',', array_unique($catIds)) . "))";
        }
    }

    // 'has_ordered' alone still needs the EXISTS wrapper, hence the extra test.
    if (!empty($orderConditions) || !empty($filters['has_ordered'])) {
        $exists = "SELECT 1 FROM " . TABLE_ORDERS . " o"
                . " JOIN " . TABLE_ORDERS_PRODUCTS . " op ON op.orders_id = o.orders_id"
                . " WHERE o.customers_id = c.customers_id";
        if (!empty($orderConditions)) {
            $exists .= ' AND ' . implode(' AND ', $orderConditions);
        }
        $sql .= " AND EXISTS (" . $exists . ")";
    } elseif (!empty($filters['never_ordered'])) {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM " . TABLE_ORDERS . " o WHERE o.customers_id = c.customers_id)";
    }

    return $sql;
}

/**
 * Count the customers a filter silently drops for want of the data it tests.
 *
 * Only asks the question when the relevant filter is actually in use -- with no
 * date bounds set, nobody is excluded for lacking a registration date.
 *
 * @return array ['missing_registration_date' => int, 'missing_address' => int]
 */
function zcEAE_count_excluded(array $filters, $db): array
{
    $counts = ['missing_registration_date' => 0, 'missing_address' => 0];

    $usingDates = !empty($filters['registered_from']) || !empty($filters['registered_to']);
    $usingPlace = !empty($filters['country_id']) || !empty($filters['zone_id'])
               || !empty($filters['state']) || !empty($filters['city']);

    foreach (['missing_registration_date' => $usingDates, 'missing_address' => $usingPlace] as $probe => $inUse) {
        if (!$inUse) {
            continue;
        }
        $sql = "SELECT COUNT(*) AS record_count" . zcEAE_filter_from() . zcEAE_filter_where($filters, $probe);
        $result = $db->Execute($sql);
        $counts[$probe] = (int)($result->fields['record_count'] ?? 0);
    }

    return $counts;
}

/**
 * Flatten zen_get_category_tree() output into ids, so a chosen category can
 * also match everything beneath it.
 */
function zcEAE_category_with_children(int $categoryId): array
{
    $ids = [$categoryId];
    // Returns the whole subtree; it also prepends the "Top" entry (id 0),
    // which the id > 0 test below discards.
    $tree = zen_get_category_tree($categoryId, '', '', [], false, false, false);
    foreach ($tree as $entry) {
        if (isset($entry['id']) && (int)$entry['id'] > 0) {
            $ids[] = (int)$entry['id'];
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Make a user-supplied filename safe to place in a Content-Disposition header
 * and on disk: no directory separators, no CR/LF header injection, and an
 * extension that matches the chosen format.
 */
function zcEAE_sanitize_filename($filename, string $extension): string
{
    $filename = (string)$filename;
    // Normalise separators first, so basename() can discard any path the admin
    // pasted in ("../../etc/passwd" -> "passwd") rather than us flattening it
    // into one long token.
    $filename = str_replace('\\', '/', $filename);
    $filename = basename($filename);
    // Anything left that could break the header or the filesystem becomes '_';
    // runs are collapsed so a stripped "\r\n" does not leave a gap.
    // (string) guards against preg_replace() returning null, which would be a
    // deprecated null argument to trim() on PHP 8.1+.
    $filename = (string)preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    $filename = (string)preg_replace('/_+/', '_', $filename);
    $filename = trim($filename, '._-');

    // Drop any extension the user typed; we append the authoritative one.
    $filename = (string)preg_replace('/\.(csv|txt|xml|json|html?)$/i', '', $filename);

    if ($filename === '') {
        $filename = 'email_addresses';
    }
    if (strlen($filename) > 100) {
        $filename = substr($filename, 0, 100);
    }
    return $filename . '.' . $extension;
}

/**
 * Neutralise spreadsheet formula injection. A leading =, +, -, @ or control
 * character makes Excel/Sheets evaluate the cell, so prefix it with an
 * apostrophe -- the conventional, lossless-on-import escape.
 */
function zcEAE_defuse_formula(string $value): string
{
    if ($value !== '' && strpos("=+-@\t\r", $value[0]) !== false) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Column keys treated as telephone numbers for output formatting.
 *
 * Named rather than positional, so it also catches a saved audience whose query
 * happens to return one of these columns.
 */
function zcEAE_phone_columns(): array
{
    return ['customers_telephone', 'customers_fax'];
}

/**
 * Normalise a customer-entered telephone number for export.
 *
 * Customers type phone numbers however they like, so a store accumulates
 * "(555) 123-4567", "555.123.4567", "+44 20 7946 0958" and "5551234567" side by
 * side. Stripping punctuation is the only transformation that is safe to apply
 * blind: it needs no knowledge of country, and it never invents information.
 *
 * Adding formatting would require knowing the dialling plan for each number,
 * which is not recorded anywhere in the customer record -- guessing it would
 * mangle every international number in the file, so it is not offered.
 *
 * Kept deliberately conservative:
 *   - a leading + is preserved, because it is the only marker distinguishing an
 *     international number from a local one;
 *   - an extension ("x123", "ext 123") is preserved, because silently merging it
 *     into the number would produce something undiallable.
 *
 * This only affects the exported file; stored data is never modified.
 *
 * @param string $mode 'raw' (unchanged) or 'digits'
 */
function zcEAE_format_phone(string $value, string $mode): string
{
    if ($mode !== 'digits' || $value === '') {
        return $value;
    }

    $value = trim($value);
    $international = (strpos($value, '+') === 0);

    // Split off an extension before stripping, so its digits do not merge into
    // the main number.
    $extension = '';
    if (preg_match('/(.*?)\s*(?:x|ext\.?|extension)\s*(\d+)\s*$/i', $value, $m)) {
        $value = $m[1];
        $extension = $m[2];
    }

    $digits = preg_replace('/\D+/', '', $value);
    $digits = (string)$digits;

    if ($digits === '') {
        return '';
    }

    return ($international ? '+' : '') . $digits . ($extension !== '' ? ' x' . $extension : '');
}

/**
 * Coerce a column name into a legal XML element name.
 *
 * In "saved audience" mode the column names come from whatever SQL the store
 * owner wrote in the Query Builder, so they can be anything at all -- an
 * unaliased `COUNT(*)`, a name with spaces, or one starting with a digit. Any
 * of those would produce a document no parser will read.
 */
function zcEAE_xml_element_name(string $name): string
{
    // Permitted: letters, digits, hyphen, underscore, period. Everything else
    // becomes an underscore.
    $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $clean = (string)$clean;
    // An element name may not begin with a digit, a period, a hyphen, or "xml".
    if ($clean === '' || !preg_match('/^[A-Za-z_]/', $clean) || stripos($clean, 'xml') === 0) {
        $clean = 'field_' . $clean;
    }
    return $clean;
}

/**
 * RFC 4180 field encoding.
 */
function zcEAE_csv_field(string $value): string
{
    $value = zcEAE_defuse_formula($value);
    return '"' . str_replace('"', '""', $value) . '"';
}

/**
 * Read one cell, applying telephone formatting where the column calls for it.
 */
function zcEAE_cell(array $row, string $key, array $phoneKeys, string $phoneMode): string
{
    $value = (string)($row[$key] ?? '');
    if ($phoneMode !== 'raw' && in_array($key, $phoneKeys, true)) {
        $value = zcEAE_format_phone($value, $phoneMode);
    }
    return $value;
}

/**
 * Render the result set in the requested format.
 *
 * @param iterable $rows      query result (queryFactoryResult is Iterator on both carts)
 * @param array    $columns   ordered column keys, used as headings
 * @param string   $phoneMode 'raw' or 'digits'; see zcEAE_format_phone()
 */
function zcEAE_render(string $format, $rows, array $columns, bool $includeHeaders, string $phoneMode = 'raw'): string
{
    $charset = defined('CHARSET') ? CHARSET : 'utf-8';
    $phoneKeys = array_values(array_intersect($columns, zcEAE_phone_columns()));
    $out = '';

    switch ($format) {
        case 'csv':
            if ($includeHeaders) {
                $out .= implode(',', array_map('zcEAE_csv_field', $columns)) . "\r\n";
            }
            foreach ($rows as $row) {
                $fields = [];
                foreach ($columns as $key) {
                    $fields[] = zcEAE_csv_field(zcEAE_cell($row, $key, $phoneKeys, $phoneMode));
                }
                $out .= implode(',', $fields) . "\r\n";
            }
            break;

        case 'txt':
            if ($includeHeaders) {
                $out .= implode("\t", $columns) . "\n";
            }
            foreach ($rows as $row) {
                $fields = [];
                foreach ($columns as $key) {
                    // Tabs/newlines inside a value would corrupt the layout.
                    $fields[] = str_replace(["\t", "\r", "\n"], ' ', zcEAE_cell($row, $key, $phoneKeys, $phoneMode));
                }
                $out .= implode("\t", $fields) . "\n";
            }
            break;

        case 'html':
            $out .= '<table border="1">' . "\n";
            if ($includeHeaders) {
                $out .= '  <tr>';
                foreach ($columns as $key) {
                    $out .= '<th>' . htmlspecialchars($key, ENT_QUOTES, $charset) . '</th>';
                }
                $out .= '</tr>' . "\n";
            }
            foreach ($rows as $row) {
                $out .= '  <tr>';
                foreach ($columns as $key) {
                    $out .= '<td>' . htmlspecialchars(zcEAE_cell($row, $key, $phoneKeys, $phoneMode), ENT_QUOTES, $charset) . '</td>';
                }
                $out .= '</tr>' . "\n";
            }
            $out .= '</table>';
            break;

        case 'xml':
            // Single well-formed document: one root, one <contact> per record,
            // every value escaped.
            $out .= '<?xml version="1.0" encoding="' . htmlspecialchars($charset, ENT_QUOTES) . '"?>' . "\n";
            $out .= "<address_book>\n";
            // Precompute element names: in audience mode these come from
            // user-authored SQL and may not be legal XML names.
            $elementNames = [];
            foreach ($columns as $key) {
                $elementNames[$key] = zcEAE_xml_element_name($key);
            }
            foreach ($rows as $row) {
                $out .= "  <contact>\n";
                foreach ($columns as $key) {
                    $tag = $elementNames[$key];
                    $out .= '    <' . $tag . '>'
                          . htmlspecialchars(zcEAE_cell($row, $key, $phoneKeys, $phoneMode), ENT_QUOTES, $charset)
                          . '</' . $tag . '>' . "\n";
                }
                $out .= "  </contact>\n";
            }
            $out .= "</address_book>\n";
            break;

        case 'json':
            $records = [];
            foreach ($rows as $row) {
                $record = [];
                foreach ($columns as $key) {
                    $record[$key] = zcEAE_cell($row, $key, $phoneKeys, $phoneMode);
                }
                $records[] = $record;
            }
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $out .= json_encode($records, $flags);
            break;
    }

    return $out;
}
