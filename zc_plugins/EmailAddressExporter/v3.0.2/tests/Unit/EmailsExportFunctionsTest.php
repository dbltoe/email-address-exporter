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
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', true);
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
}
