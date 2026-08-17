<?php

/**
 * Unit tests for the Email Address Exporter helper functions.
 *
 * Discovered automatically by Zen Cart v3.0.0's plugin test runner:
 *     composer tests-plugin -- --plugin EmailAddressExporter
 *
 * These cover the pure helpers only -- no database, no cart bootstrap -- which is
 * what makes them fast enough to run on every edit. The helper file guards itself
 * with IS_ADMIN_FLAG, so that has to be defined before it is required.
 *
 * @copyright Portions Copyright 2026 My Zen Cart Host (dbltoe)
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', true);
}

// zcEAE_filter_where() and zcEAE_render() reach for cart constants and for
// zen_db_input(). Stubbing them is what lets the two functions that actually
// build the query and the file be tested without a database or a bootstrap --
// which is where the coverage gap was: every helper below the query builder was
// tested, and the query builder itself was not.
foreach ([
    'CHARSET' => 'utf-8',
    'TABLE_CUSTOMERS' => 'customers',
    'TABLE_ADDRESS_BOOK' => 'address_book',
    'TABLE_COUNTRIES' => 'countries',
    'TABLE_ZONES' => 'zones',
    'TABLE_CUSTOMERS_INFO' => 'customers_info',
    'TABLE_ORDERS' => 'orders',
    'TABLE_ORDERS_PRODUCTS' => 'orders_products',
    'TABLE_PRODUCTS_TO_CATEGORIES' => 'products_to_categories',
] as $constantName => $constantValue) {
    if (!defined($constantName)) {
        define($constantName, $constantValue);
    }
}

if (!function_exists('zen_db_input')) {
    // Stands in for $db->prepare_input() (mysqli_real_escape_string). These
    // tests assert query shape, not escaping.
    function zen_db_input($string)
    {
        return addslashes((string)$string);
    }
}

require_once __DIR__ . '/../../admin/includes/emails_export_functions.php';

final class EmailsExportFunctionsTest extends TestCase
{
    // ------------------------------------------------------------------ decode_input

    /**
     * The trap this plugin exists to survive: Zen Cart's AdminRequestSanitizer runs
     * htmlspecialchars() over every POST value a plugin invents, on 1.5.8 through
     * 3.0.0 alike. A city typed "Stoke & Trent" arrives as "Stoke &amp; Trent" and
     * matching it against the database returns nothing at all.
     */
    public function testDecodeInputReversesAdminSanitizing(): void
    {
        self::assertSame('Stoke & Trent', zcEAE_decode_input('Stoke &amp; Trent'));
        self::assertSame('O"Brien', zcEAE_decode_input('O&quot;Brien'));
        self::assertSame("O'Brien", zcEAE_decode_input('O&#039;Brien'));
        self::assertSame('<tag>', zcEAE_decode_input('&lt;tag&gt;'));
    }

    /**
     * Double-encoding is on, so a value that genuinely contained "&amp;" survives a
     * single decode. If this ever fails, decoding twice somewhere is corrupting data.
     */
    public function testDecodeInputRoundTripsLiteralEntities(): void
    {
        self::assertSame('AT&amp;T', zcEAE_decode_input('AT&amp;amp;T'));
    }

    public function testDecodeInputLeavesPlainTextAlone(): void
    {
        self::assertSame('Manchester', zcEAE_decode_input('Manchester'));
        self::assertSame('', zcEAE_decode_input(''));
    }

    // ------------------------------------------------------------------ columns

    public function testSanitizeColumnsAlwaysKeepsEmailAddress(): void
    {
        self::assertSame(['customers_email_address'], zcEAE_sanitize_columns([]));
        self::assertContains('customers_email_address', zcEAE_sanitize_columns(['city']));
    }

    public function testSanitizeColumnsDiscardsUnknownKeys(): void
    {
        $result = zcEAE_sanitize_columns(['city', 'not_a_column', 'DROP TABLE customers']);

        self::assertContains('city', $result);
        self::assertNotContains('not_a_column', $result);
        self::assertNotContains('DROP TABLE customers', $result);
    }

    /**
     * Output order must follow the registry, not the order the admin ticked boxes,
     * so exported files have stable column ordering between runs.
     */
    public function testSanitizeColumnsFollowsRegistryOrder(): void
    {
        $result = zcEAE_sanitize_columns(['postcode', 'customers_firstname']);

        self::assertSame(
            ['customers_email_address', 'customers_firstname', 'postcode'],
            $result
        );
    }

    public function testSanitizeColumnsToleratesNonArray(): void
    {
        self::assertSame(['customers_email_address'], zcEAE_sanitize_columns('city'));
        self::assertSame(['customers_email_address'], zcEAE_sanitize_columns(null));
    }

    // ------------------------------------------------------------------ dates

    #[DataProvider('provideDates')]
    public function testCleanDate(string $input, string $expected): void
    {
        self::assertSame($expected, zcEAE_clean_date($input));
    }

    public static function provideDates(): array
    {
        return [
            'valid'              => ['2026-08-10', '2026-08-10'],
            'leap day valid'     => ['2024-02-29', '2024-02-29'],
            'leap day invalid'   => ['2026-02-29', ''],
            'impossible day'     => ['2026-02-30', ''],
            'month 13'           => ['2026-13-01', ''],
            'wrong format'       => ['10/08/2026', ''],
            'partial'            => ['2026-08', ''],
            'empty'              => ['', ''],
            'sql injection'      => ["2026-08-10' OR '1'='1", ''],
        ];
    }

    // ------------------------------------------------------------------ filenames

    public function testSanitizeFilenameStripsPathTraversal(): void
    {
        self::assertSame('passwd.csv', zcEAE_sanitize_filename('../../etc/passwd', 'csv'));
        self::assertSame('secret.csv', zcEAE_sanitize_filename('..\\..\\windows\\secret', 'csv'));
    }

    public function testSanitizeFilenameStripsHeaderInjection(): void
    {
        $result = zcEAE_sanitize_filename("report\r\nContent-Type: text/html", 'csv');

        self::assertStringNotContainsString("\r", $result);
        self::assertStringNotContainsString("\n", $result);
        self::assertStringEndsWith('.csv', $result);
    }

    public function testSanitizeFilenameReplacesUserSuppliedExtension(): void
    {
        self::assertSame('customers.csv', zcEAE_sanitize_filename('customers.txt', 'csv'));
        self::assertSame('customers.xml', zcEAE_sanitize_filename('customers.xml', 'xml'));
    }

    public function testSanitizeFilenameFallsBackWhenNothingUsableRemains(): void
    {
        self::assertSame('email_addresses.csv', zcEAE_sanitize_filename('', 'csv'));
        self::assertSame('email_addresses.csv', zcEAE_sanitize_filename('...', 'csv'));
    }

    public function testSanitizeFilenameCapsLength(): void
    {
        $result = zcEAE_sanitize_filename(str_repeat('a', 300), 'csv');

        self::assertSame(104, strlen($result));   // 100 + '.csv'
    }

    // ------------------------------------------------------------------ formula injection

    #[DataProvider('provideFormulaPrefixes')]
    public function testDefuseFormulaPrefixesDangerousLeadingCharacters(string $input): void
    {
        self::assertSame("'" . $input, zcEAE_defuse_formula($input));
    }

    public static function provideFormulaPrefixes(): array
    {
        return [
            'equals' => ['=1+1'],
            'plus'   => ['+1'],
            'minus'  => ['-1'],
            'at'     => ['@SUM(A1)'],
            'tab'    => ["\tvalue"],
            'return' => ["\rvalue"],
            'cmd'    => ['=cmd|\' /C calc\'!A0'],
        ];
    }

    public function testDefuseFormulaLeavesOrdinaryValuesAlone(): void
    {
        self::assertSame('someone@example.com', zcEAE_defuse_formula('someone@example.com'));
        self::assertSame('Smith', zcEAE_defuse_formula('Smith'));
        self::assertSame('', zcEAE_defuse_formula(''));
    }

    // ------------------------------------------------------------------ csv encoding

    public function testCsvFieldQuotesAndDoublesEmbeddedQuotes(): void
    {
        self::assertSame('"plain"', zcEAE_csv_field('plain'));
        self::assertSame('"say ""hi"""', zcEAE_csv_field('say "hi"'));
        self::assertSame('"a,b"', zcEAE_csv_field('a,b'));
    }

    public function testCsvFieldAlsoDefusesFormulas(): void
    {
        self::assertSame('"\'=1+1"', zcEAE_csv_field('=1+1'));
    }

    // ------------------------------------------------------------------ phone formatting

    #[DataProvider('providePhones')]
    public function testFormatPhoneDigitsMode(string $input, string $expected): void
    {
        self::assertSame($expected, zcEAE_format_phone($input, 'digits'));
    }

    public static function providePhones(): array
    {
        return [
            'us punctuation'   => ['(555) 123-4567', '5551234567'],
            'dots'             => ['555.123.4567', '5551234567'],
            'already digits'   => ['5551234567', '5551234567'],
            'international'    => ['+44 20 7946 0958', '+442079460958'],
            'extension x'      => ['555-1234 x89', '5551234 x89'],
            'extension ext'    => ['555-1234 ext. 89', '5551234 x89'],
            'letters only'     => ['CALL ME', ''],
            'empty'            => ['', ''],
        ];
    }

    public function testFormatPhoneRawModeIsUntouched(): void
    {
        self::assertSame('(555) 123-4567', zcEAE_format_phone('(555) 123-4567', 'raw'));
    }

    // ------------------------------------------------------------------ xml element names

    #[DataProvider('provideXmlNames')]
    public function testXmlElementName(string $input, string $expected): void
    {
        self::assertSame($expected, zcEAE_xml_element_name($input));
    }

    public static function provideXmlNames(): array
    {
        return [
            'already legal'   => ['customers_email_address', 'customers_email_address'],
            'spaces'          => ['first name', 'first_name'],
            'aggregate'       => ['COUNT(*)', 'COUNT___'],
            'leading digit'   => ['1st_choice', 'field_1st_choice'],
            'reserved xml'    => ['xmlData', 'field_xmlData'],
            'empty'           => ['', 'field_'],
        ];
    }

    // ------------------------------------------------------------------ purchase-history filters

    /**
     * Filters with no purchase criteria at all, so no order test is emitted.
     */
    private static function filters(array $overrides = []): array
    {
        return array_merge([
            'newsletter_only'  => false,
            'exclude_no_email' => false,
            'country_id'       => 0,
            'zone_id'          => 0,
            'state'            => '',
            'city'             => '',
            'registered_from'  => '',
            'registered_to'    => '',
            'ordered_from'     => '',
            'ordered_to'       => '',
            'orders_status'    => 0,
            'products_id'      => 0,
            'categories_ids'   => [],
            'has_ordered'      => false,
            'never_ordered'    => false,
        ], $overrides);
    }

    /**
     * The regression this suite exists for.
     *
     * "Only customers who have never placed an order" used to be tested in an
     * elseif AFTER the positive case, so any other purchase filter -- a status,
     * a product, a category, a date -- claimed the EXISTS branch first and the
     * export returned precisely the customers who HAD bought, reporting success.
     */
    #[DataProvider('provideNeverOrderedCombinations')]
    public function testNeverOrderedAlwaysNegatesTheOrderTest(array $overrides): void
    {
        $sql = zcEAE_filter_where(self::filters($overrides));

        self::assertStringContainsString('NOT EXISTS', $sql);
        // Guard against a "NOT EXISTS ... AND EXISTS ..." regression rather than
        // just checking that the negation appears somewhere.
        self::assertSame(1, substr_count($sql, 'EXISTS'));
    }

    public static function provideNeverOrderedCombinations(): array
    {
        return [
            'alone'            => [['never_ordered' => true]],
            'with status'      => [['never_ordered' => true, 'orders_status' => 3]],
            'with product'     => [['never_ordered' => true, 'products_id' => 42]],
            'with category'    => [['never_ordered' => true, 'categories_ids' => [7]]],
            'with date from'   => [['never_ordered' => true, 'ordered_from' => '2026-01-01']],
            'with date to'     => [['never_ordered' => true, 'ordered_to' => '2026-01-01']],
            'with every other' => [[
                'never_ordered' => true,
                'orders_status' => 3,
                'products_id'   => 42,
                'ordered_from'  => '2026-01-01',
            ]],
        ];
    }

    public function testHasOrderedUsesPositiveExists(): void
    {
        $sql = zcEAE_filter_where(self::filters(['has_ordered' => true]));

        self::assertStringContainsString('EXISTS', $sql);
        self::assertStringNotContainsString('NOT EXISTS', $sql);
    }

    public function testPurchaseFilterAloneImpliesHavingOrdered(): void
    {
        $sql = zcEAE_filter_where(self::filters(['orders_status' => 3]));

        self::assertStringContainsString('EXISTS', $sql);
        self::assertStringNotContainsString('NOT EXISTS', $sql);
    }

    public function testNoPurchaseFilterEmitsNoOrderTest(): void
    {
        self::assertStringNotContainsString('EXISTS', zcEAE_filter_where(self::filters()));
    }

    /**
     * The orders_products join exists only to support the product and category
     * tests. Joining it unconditionally made "never ordered" quietly mean "has
     * no order carrying line items".
     */
    public function testOrdersProductsIsJoinedOnlyWhenAProductTestNeedsIt(): void
    {
        self::assertStringNotContainsString(
            'orders_products',
            zcEAE_filter_where(self::filters(['never_ordered' => true]))
        );
        self::assertStringNotContainsString(
            'orders_products',
            zcEAE_filter_where(self::filters(['orders_status' => 3]))
        );
        self::assertStringContainsString(
            'orders_products',
            zcEAE_filter_where(self::filters(['products_id' => 42]))
        );
        self::assertStringContainsString(
            'orders_products',
            zcEAE_filter_where(self::filters(['categories_ids' => [7]]))
        );
    }

    /**
     * A categories_ids list that survives no id > 0 must not leave a dangling
     * join behind.
     */
    public function testEmptyCategoryListAddsNoProductTest(): void
    {
        self::assertStringNotContainsString(
            'orders_products',
            zcEAE_filter_where(self::filters(['categories_ids' => [0, -1]]))
        );
    }

    // ------------------------------------------------------------------ rendering

    private const ROW = [
        'customers_email_address' => 'someone@example.com',
        'customers_lastname'      => 'Smith',
    ];

    private const COLUMNS = ['customers_email_address', 'customers_lastname'];

    /**
     * JSON is defined as UTF-8, and json_encode() signals data it cannot encode
     * by returning false -- which used to concatenate as '' and hand the admin a
     * zero-byte file alongside a success message. An iso-8859-1 store is the
     * everyday way to hit it.
     */
    public function testJsonRenderSurvivesNonUtf8Data(): void
    {
        $rows = [['customers_email_address' => 'a@b.com', 'customers_lastname' => "J\xF6rg"]];

        $out = zcEAE_render('json', $rows, self::COLUMNS, true, 'raw');

        self::assertNotSame('', $out, 'JSON export must never be silently empty');
        self::assertIsArray(json_decode($out, true));
    }

    public function testJsonRenderProducesDecodableOutput(): void
    {
        $decoded = json_decode(zcEAE_render('json', [self::ROW], self::COLUMNS, true, 'raw'), true);

        self::assertSame('someone@example.com', $decoded[0]['customers_email_address']);
        self::assertSame('Smith', $decoded[0]['customers_lastname']);
    }

    /**
     * Tab-delimited files get opened in Excel too, so the formula guard has to
     * cover TXT and not just CSV.
     */
    public function testTxtRenderDefusesFormulas(): void
    {
        $rows = [['customers_email_address' => 'a@b.com', 'customers_lastname' => '=HYPERLINK("http://evil")']];

        $out = zcEAE_render('txt', $rows, self::COLUMNS, false, 'raw');

        self::assertStringContainsString("'=HYPERLINK", $out);
    }

    /**
     * Defusing has to happen before tabs are collapsed to spaces, or a value
     * starting with a tab loses the very character that marked it dangerous.
     */
    public function testTxtRenderDefusesLeadingTabBeforeCollapsingIt(): void
    {
        $rows = [['customers_email_address' => 'a@b.com', 'customers_lastname' => "\t=1+1"]];

        $out = zcEAE_render('txt', $rows, self::COLUMNS, false, 'raw');

        self::assertStringContainsString("'", $out);
        self::assertSame(1, substr_count($out, "\t"), 'only the field separator may remain');
    }

    public function testXmlRenderIsWellFormed(): void
    {
        $rows = [['customers_email_address' => 'a&b@example.com', 'customers_lastname' => '<Smith>']];

        $out = zcEAE_render('xml', $rows, self::COLUMNS, true, 'raw');

        self::assertNotFalse(simplexml_load_string($out), 'XML export must parse');
    }

    public function testCsvRenderQuotesEveryField(): void
    {
        $rows = [['customers_email_address' => 'a@b.com', 'customers_lastname' => "O'Hara, Jr."]];

        $out = zcEAE_render('csv', $rows, self::COLUMNS, false, 'raw');

        self::assertSame("\"a@b.com\",\"O'Hara, Jr.\"\r\n", $out);
    }
}
