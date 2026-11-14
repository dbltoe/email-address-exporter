<?php
/**
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

$define = [
    'HEADING_TITLE' => 'Export Email Addresses',

    // --- Section headings (rendered bold, so Title Case + colon) ---
    // The filters sit under "Who To Export:" with their own subheadings, so no
    // separate "Filters:" heading is needed.
    'TEXT_LEGEND_SOURCE'      => 'Who To Export:',
    'TEXT_LEGEND_LOCATION'    => 'Location:',
    'TEXT_LEGEND_REGISTERED'  => 'Registration Date:',
    'TEXT_LEGEND_PURCHASES'   => 'Purchase History:',
    'TEXT_LEGEND_COLUMNS'     => 'Columns To Export:',
    'TEXT_LEGEND_OUTPUT'      => 'Output:',

    // --- Mode selection ---
    'TEXT_MODE_FILTERS'  => 'Build a list using the filters below',
    'TEXT_MODE_AUDIENCE' => 'Use a saved audience (Query Builder)',

    'TEXT_PLEASE_SELECT_AUDIENCE' => 'Saved Audience:',
    'TEXT_CHOOSE_AUDIENCE'        => '-- please select --',
    'TEXT_AUDIENCE_HELP'          => 'Saved audiences come from Tools &gt; Query Builder. The exported columns are whatever that saved query returns, so the column checkboxes below do not apply in this mode.',

    // --- Explains what an untouched control does ---
    'TEXT_FILTERS_INTRO' => '<strong>Every Filter Below Is Optional:</strong> a filter you leave blank, or leave set to its &ldquo;-- any --&rdquo; choice, is not applied at all. Leave the whole section alone and you get every customer allowed by the two consent options above. Each filter you do set narrows the list further &mdash; they combine, so a customer must satisfy all of them to be exported.',

    // --- Consent filters ---
    'TEXT_NEWSLETTER_ONLY'  => 'Only customers subscribed to the newsletter',
    'TEXT_EXCLUDE_NO_EMAIL' => 'Exclude customers whose email format is set to "NONE"',
    'TEXT_CONSENT_WARNING'  => '<strong>Please Note:</strong> these two options are what keep an export limited to people who agreed to be emailed. Turning them off exports addresses regardless of consent &mdash; make sure you have a lawful basis (GDPR, CAN-SPAM, CASL, etc.) before doing so.',

    // --- Location filters ---
    'TEXT_FILTER_COUNTRY'    => 'Country:',
    'TEXT_FILTER_STATE'      => 'State / Zone:',
    'TEXT_FILTER_CITY'       => 'City:',
    'TEXT_ANY_COUNTRY'       => '-- any country --',
    'TEXT_ANY_STATE'         => '-- any state / zone --',
    'TEXT_COUNTRY_HELP'      => 'Leave on &ldquo;any country&rdquo; to include every country. Choosing one reloads the page so the state list can be filled in.',
    'TEXT_STATE_HELP'        => 'Leave blank to include every state or zone.',
    'TEXT_CITY_HELP'         => 'Partial match, so &ldquo;york&rdquo; finds both York and New York. Leave blank to include every city.',
    'TEXT_STATE_PLACEHOLDER' => 'Partial match on the free-text state field',

    // --- Date filters ---
    'TEXT_FILTER_REGISTERED_FROM' => 'Registered From:',
    'TEXT_FILTER_REGISTERED_TO'   => 'Registered To:',
    'TEXT_FILTER_ORDERED_FROM'    => 'Ordered From:',
    'TEXT_FILTER_ORDERED_TO'      => 'Ordered To:',
    'TEXT_DATE_HELP'              => 'Leave both blank for no date limit. Fill in only &ldquo;From&rdquo; for on-or-after that date, or only &ldquo;To&rdquo; for on-or-before it &mdash; an open end reaches back to your very first record, or forward to today.',
    'TEXT_REGISTERED_DATE_NOTE'   => '<strong>Worth Knowing:</strong> a customer whose registration date was never recorded &mdash; typically one added by an admin or brought in by an import &mdash; cannot satisfy a date test, so setting either box here leaves them out. Leave both blank if you need those customers included. Use <em>Refresh Filter Options</em> to see how many that affects before you export.',

    // %d is the number of customers dropped for want of the data being tested.
    'TEXT_EXCLUDED_NO_REG_DATE' => '<strong>Excluded From This Export:</strong> %d customer(s) match everything else you have chosen but have no recorded registration date, so the date filter above leaves them out. Clear both date boxes to include them.',
    'TEXT_EXCLUDED_NO_ADDRESS'  => '<strong>Excluded From This Export:</strong> %d customer(s) match everything else you have chosen but have no default address on file, so the location filter above leaves them out. Clear the location boxes to include them.',

    // --- Purchase filters ---
    'TEXT_HAS_ORDERED'           => 'Only customers who have placed at least one order',
    'TEXT_NEVER_ORDERED'         => 'Only customers who have never placed an order',
    'TEXT_PURCHASE_HELP'         => 'Leave everything in this section untouched to ignore purchase history entirely. If you set more than one, the same order must satisfy all of them.',
    'TEXT_FILTER_ORDER_STATUS'   => 'Order Status:',
    'TEXT_ANY_ORDER_STATUS'      => '-- any status --',
    'TEXT_FILTER_CATEGORY'       => 'Purchased From Category:',
    'TEXT_ANY_CATEGORY'          => '-- any category --',
    'TEXT_INCLUDE_SUBCATEGORIES' => 'Include subcategories',
    'TEXT_FILTER_PRODUCT_ID'     => 'Purchased Product ID:',
    'TEXT_PRODUCT_ID_HELP'       => 'Numeric product ID, as shown in Catalog &gt; Products. Leave blank for any product.',

    // --- Output ---
    'TEXT_EMAIL_EXPORT_FORMAT'   => 'Export File Format:',
    'TEXT_EMAIL_EXPORT_FILENAME' => 'Export Filename:',
    'TEXT_FILENAME_HELP'         => 'Leave blank to use &ldquo;email_addresses&rdquo;. The correct extension is added automatically to match the chosen format.',
    'TEXT_PHONE_FORMAT'          => 'Telephone Format:',
    'TEXT_PHONE_AS_STORED'       => 'As stored (leave numbers exactly as typed)',
    'TEXT_PHONE_DIGITS'          => 'Digits only (strip brackets, spaces, dashes and dots)',
    'TEXT_PHONE_HELP'            => 'Customer-entered phone numbers are rarely consistent. &ldquo;Digits only&rdquo; strips the punctuation so every number comes out comparable. A leading + is kept for international numbers, and an extension written as &ldquo;x123&rdquo; is preserved. Only affects the exported file &mdash; your stored data is never changed.',
    'TEXT_INCLUDE_HEADERS'       => 'Include a header row / column names',
    'TEXT_ADD_BOM'               => 'Add a UTF-8 BOM (CSV only &mdash; helps Excel show accented characters correctly)',

    'TEXT_COLUMNS_HELP'      => 'The email address is always included and cannot be unticked. Address columns come from each customer&rsquo;s default address; a customer with no address book entry is still exported, with those columns left empty.',
    'TEXT_REFRESH_OPTIONS'   => 'Refresh filter options',
    'TEXT_RECORDS_PROCESSED' => '%d records exported.',

    // --- Errors ---
    'ERROR_PLEASE_SELECT_AUDIENCE'    => 'Please select a saved audience to export.',
    'ERROR_NO_SUCH_QUERY'             => 'That saved audience no longer exists in the Query Builder.',
    'ERROR_NO_COLUMNS_SELECTED'       => 'Please select at least one column to export.',
    'ERROR_NO_RECORDS_FOUND'          => 'No records matched those filters. Nothing was exported.',
    'ERROR_CONFLICTING_ORDER_FILTERS' => 'You cannot select both "has placed at least one order" and "has never placed an order".',

    'TEXT_INSTRUCTIONS' => '
<p>Export customer email addresses to a file you can import into a mailing tool, spreadsheet or address book.</p>
<ol>
<li>Choose whether to build the list from the <em>filters</em> on this page, or from a <em>saved audience</em> defined in Tools &gt; Query Builder.</li>
<li>Narrow the list down &mdash; by location, registration date, and what the customer has bought.</li>
<li>Tick the columns you want in the file.</li>
<li>Pick an output format and filename, then click Go.</li>
</ol>
<p>CSV, TXT, XML and JSON download as a file. HTML is displayed on screen instead.</p>',
];

return $define;
