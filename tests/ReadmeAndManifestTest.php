<?php

/**
 * Unit tests for the README.md guide, the admin ReadMe page that shows it, the
 * manifest's button row, and the license lines.
 *
 * Discovered automatically by Zen Cart v3.0.0's plugin test runner:
 *     composer tests-plugin -- --plugin EmailAddressExporter
 *
 * README.md is Markdown, kept in the plugin's version folder and at the root of the
 * download (byte-identical). It isn't a public file: the ReadMe button in Plugin
 * Manager opens it through admin/email_address_exporter_readme.php, behind the admin
 * login, for superusers only.
 *
 * No database and no cart bootstrap. The manifest tests each run in their own process,
 * because each needs its own zen_is_superuser().
 *
 * @copyright Portions Copyright 2026 My Zen Cart Host (dbltoe)
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ReadmeAndManifestTest extends TestCase
{
    private const README_PAGE = 'admin/email_address_exporter_readme.php';
    private const LICENSE_LINE = ' * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0';

    /**
     * This test lives at the repository root (tests/), outside the plugin, so it isn't in
     * the download. The plugin's one version folder is found from there.
     */
    private static function pluginDir(): string
    {
        $dirs = glob(dirname(__DIR__) . '/zc_plugins/EmailAddressExporter/v*', GLOB_ONLYDIR) ?: [];
        self::assertCount(1, $dirs, 'exactly one version folder');
        return str_replace('\\', '/', $dirs[0]);
    }

    /**
     * The root of the download, three levels above the version folder. Inside a store
     * that same path is the store's root, which is somebody else's; so this answers
     * null unless it looks like this plugin's own repository or package.
     */
    private static function packageRoot(): ?string
    {
        $root = dirname(self::pluginDir(), 3);
        if (is_file($root . '/README.md') && is_file($root . '/LICENSE') && !is_dir($root . '/includes')) {
            return $root;
        }
        return null;
    }

    private static function readme(): string
    {
        return (string)file_get_contents(self::pluginDir() . '/README.md');
    }

    private static function page(): string
    {
        return (string)file_get_contents(self::pluginDir() . '/' . self::README_PAGE);
    }

    /**
     * The renderer is two functions in the middle of the admin page; the page itself
     * cannot be included here (it needs the admin). Lift the two functions out, into a
     * throwaway file, and load that.
     */
    private static function render(string $markdown): string
    {
        if (!function_exists('eae_markdown_html')) {
            $page = self::page();
            $from = strpos($page, "if (!function_exists('eae_markdown_html'))");
            $to = strpos($page, '$eaeReadmeFile');
            self::assertNotFalse($from);
            self::assertNotFalse($to);
            $file = tempnam(sys_get_temp_dir(), 'eae');
            file_put_contents($file, "<?php\n" . substr($page, $from, $to - $from));
            require $file;
            unlink($file);
        }
        return eae_markdown_html($markdown);
    }

    /**
     * @return string[] every PHP file in the plugin, as a path relative to it
     */
    private static function phpFiles(): array
    {
        $files = [];
        $base = self::pluginDir();
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $files[] = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
            }
        }
        sort($files);
        return $files;
    }

    // ------------------------------------------------------------------ README.md

    public function testReadmeIsMarkdownInThePluginAndReadmeHtmlIsGone(): void
    {
        self::assertFileExists(self::pluginDir() . '/README.md');
        self::assertFileDoesNotExist(self::pluginDir() . '/readme.html');
    }

    public function testPackageRootReadmeIsByteIdentical(): void
    {
        $root = self::packageRoot();
        if ($root === null) {
            self::markTestSkipped('Not running from the plugin\'s own repository or package.');
        }
        self::assertFileDoesNotExist($root . '/readme.html');
        self::assertSame(md5_file(self::pluginDir() . '/README.md'), md5_file($root . '/README.md'));
        self::assertStringContainsString('Version 2, June 1991', (string)file_get_contents($root . '/LICENSE'));
    }

    public function testReadmeOpensWithTheAgreedTitleBlock(): void
    {
        $version = ltrim(basename(self::pluginDir()), 'v');
        $lines = preg_split('/\r\n|\n/', self::readme());

        self::assertSame('# Email Address Exporter', $lines[0]);
        self::assertSame('', $lines[1]);
        self::assertSame(
            'Version ' . $version . ' · Zen Cart 1.5.8 through 3.0.0 · PHP 7.4 through 8.5 · GPL v2 · by My Zen Cart Host (dbltoe)',
            $lines[2]
        );
    }

    public function testEveryPathTheReadmeQuotesUsesThisVersionFolder(): void
    {
        preg_match_all('~zc_plugins/EmailAddressExporter/(v[0-9.]+)~', self::readme(), $m);

        self::assertNotEmpty($m[1]);
        self::assertSame([basename(self::pluginDir())], array_values(array_unique($m[1])));
    }

    public function testReadmeNamesTheLicenseAndTheReadMeButton(): void
    {
        $readme = self::readme();

        self::assertStringContainsString('GNU General Public License, version 2', $readme);
        self::assertStringContainsString('https://www.gnu.org/licenses/old-licenses/gpl-2.0.html', $readme);
        self::assertStringContainsString('Copyright © 2026 My Zen Cart Host (dbltoe).', $readme);
        self::assertStringContainsString(
            'a superuser can open this guide from the **ReadMe** button in Plugin Manager',
            $readme
        );
        self::assertMatchesRegularExpression('~^## Changelog\R\RSee `CHANGELOG\.md`\.~m', $readme);
        self::assertMatchesRegularExpression('~^## License\R~m', $readme);
        self::assertDoesNotMatchRegularExpression('~GPL ?v?3(?![.\d])|gpl-3\.0|zen-cart\.com/license~i', $readme);
    }

    /**
     * Only what the admin page's renderer understands: no raw HTML, no [text](url)
     * links, no images, no nested lists, nothing deeper than ###.
     */
    public function testReadmeUsesOnlyTheMarkdownSubsetThePageRenders(): void
    {
        // Code blocks and code spans may hold anything; look at the rest.
        $prose = preg_replace('~^```.*?^```~ms', '', self::readme());
        $prose = preg_replace('~`[^`\r\n]+`~', '', $prose);

        self::assertDoesNotMatchRegularExpression('~<[a-z/!][^>]*>~i', $prose, 'raw HTML');
        self::assertDoesNotMatchRegularExpression('~\]\(~', $prose, '[text](url) link or image');
        self::assertDoesNotMatchRegularExpression('~^#{4,}~m', $prose, 'heading deeper than ###');
        self::assertDoesNotMatchRegularExpression('~^[ \t]+(-|\d+\.)[ \t]~m', $prose, 'nested list');
        self::assertDoesNotMatchRegularExpression('~&[a-z]+;~i', $prose, 'HTML entity');
    }

    // ------------------------------------------------------------------ the admin ReadMe page

    public function testReadmePageDoesNotCollideWithTheToolsOwnPage(): void
    {
        self::assertFileExists(self::pluginDir() . '/' . self::README_PAGE);
        self::assertFileExists(self::pluginDir() . '/admin/emails_export.php');
        self::assertNotSame('emails_export.php', basename(self::README_PAGE));
        self::assertSame(
            ['email_address_exporter_readme.php', 'emails_export.php'],
            array_map('basename', glob(self::pluginDir() . '/admin/*.php'))
        );
    }

    public function testReadmePageIsGuardedBeforeItDoesAnything(): void
    {
        $page = self::page();
        $guard = strpos($page, "if (!defined('IS_ADMIN_FLAG'))");
        $top = strpos($page, "require 'includes/application_top.php';");
        $super = strpos($page, "if (!function_exists('zen_is_superuser') || !zen_is_superuser())");

        self::assertNotFalse($guard);
        self::assertNotFalse($top);
        self::assertNotFalse($super);
        self::assertTrue($guard < $top && $top < $super, '404 guard, then application_top, then superusers only');
        self::assertStringContainsString('FILENAME_DENIED', $page);
    }

    public function testReadmePageSendsStrictHeaders(): void
    {
        $page = self::page();

        foreach ([
            "header('Content-Type: text/html; charset=utf-8');",
            "header('X-Content-Type-Options: nosniff');",
            "header('X-Frame-Options: DENY');",
            "header(\"Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'; sandbox allow-popups allow-popups-to-escape-sandbox\");",
            "header('Referrer-Policy: no-referrer');",
        ] as $line) {
            self::assertStringContainsString($line, $page);
        }
        self::assertStringNotContainsStringIgnoringCase('<script', $page);
    }

    public function testReadmePageReadsOnlyItsOwnReadmeAndEchoesOnlyTheRender(): void
    {
        $page = self::page();

        self::assertSame(1, substr_count($page, 'file_get_contents('));
        self::assertStringContainsString('$eaeReadmeFile = dirname(__DIR__) . \'/README.md\';', $page);
        self::assertStringContainsString('file_get_contents($eaeReadmeFile)', $page);
        self::assertSame(1, substr_count($page, 'echo '));
        self::assertStringContainsString('<?php echo $eaeReadmeHtml; ?>', $page);
        self::assertStringContainsString('$text = htmlspecialchars((string)$text, ENT_QUOTES, \'UTF-8\');', $page);
        self::assertDoesNotMatchRegularExpression('~\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)~', $page);
    }

    public function testGuideRendersWithOnlyTheTagsTheRendererMakes(): void
    {
        $readme = self::readme();
        $html = self::render($readme);

        self::assertSame(1, substr_count($html, '<h1>'));
        self::assertSame(preg_match_all('~^## ~m', $readme), substr_count($html, '<h2>'));
        self::assertSame(preg_match_all('~^### ~m', $readme), substr_count($html, '<h3>'));
        self::assertStringContainsString('<th>Filter</th>', $html);
        self::assertStringContainsString('<td>Purchased product ID</td>', $html);
        self::assertStringContainsString('<pre><code>&lt;your store root&gt;/zc_plugins/EmailAddressExporter/', $html);
        self::assertStringContainsString('<code>&lt;address_book&gt;</code>', $html);
        self::assertSame(
            0,
            preg_match('~<(?!/?(h[1-3]|p|ul|ol|li|blockquote|pre|code|table|tr|th|td|strong|em|a)[ >])~', $html),
            'a tag the renderer does not make'
        );
        self::assertStringNotContainsString('**', $html, 'unrendered bold');
        self::assertStringNotContainsString('`', $html, 'unrendered code span');
    }

    public function testEveryLinkInTheGuideOpensInANewWindowWithNoOpener(): void
    {
        $html = self::render(self::readme());

        self::assertGreaterThan(0, preg_match_all('~<a ~', $html));
        self::assertSame(
            preg_match_all('~<a ~', $html),
            preg_match_all('~<a href="https://[^"]+" target="_blank" rel="noopener noreferrer">~', $html)
        );
    }

    public function testMarkupInTheFileComesOutEscaped(): void
    {
        $evil = self::render(
            "# <script>alert(1)</script>\n\n<img src=x onerror=alert(1)> [x](javascript:alert(1))\n\n"
            . "| <b>a</b> | `<i>` |\n| --- | --- |\n| \"onmouseover=\"x | javascript:alert(1) |\n\n"
            . "```\n</code></pre><script>x</script>\n```\n"
        );

        self::assertStringNotContainsStringIgnoringCase('<script', $evil);
        self::assertStringNotContainsStringIgnoringCase('<img', $evil);
        self::assertStringNotContainsStringIgnoringCase('<b>', $evil);
        self::assertStringNotContainsStringIgnoringCase('<i>', $evil);
        self::assertStringNotContainsString('href="javascript', $evil);
        self::assertStringNotContainsString('"onmouseover', $evil);
    }

    // ------------------------------------------------------------------ manifest.php

    /**
     * Before any admin function exists (a plain read): the ReadMe button is in the row
     * and opens the admin page, not a public file.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testManifestButtonRow(): void
    {
        if (function_exists('zen_is_superuser')) {
            self::markTestSkipped('zen_is_superuser() is already defined in this process.');
        }
        $src = (string)file_get_contents(self::pluginDir() . '/manifest.php');
        $manifest = require self::pluginDir() . '/manifest.php';
        $desc = $manifest['pluginDescription'];

        self::assertSame(basename(self::pluginDir()), $manifest['pluginVersion']);
        self::assertSame('My Zen Cart Host (dbltoe)', $manifest['pluginAuthor']);
        self::assertStringContainsString(
            '<a href="index.php?cmd=email_address_exporter_readme" target="_blank" rel="noopener noreferrer"',
            $desc
        );
        self::assertStringNotContainsString('readme.html', $src);
        self::assertStringNotContainsString('zc_plugins/EmailAddressExporter/v', $src);
        self::assertDoesNotMatchRegularExpression('~/v\d+\.\d+\.\d+/~', $desc, 'a version folder in a link');

        preg_match_all('~role="button"[^>]*>([^<]+)</a>~', $desc, $labels);
        self::assertSame(['ReadMe', 'GitHub', 'Forum Support Thread'], $labels[1]);
        self::assertStringNotContainsString('>Settings<', $desc, 'the page is the tool itself: no Settings button');

        // ReadMe, GitHub, the forum thread and the release notes: every one in a new
        // window, with no opener and no referrer.
        self::assertSame(4, substr_count($desc, '<a '));
        self::assertSame(4, substr_count($desc, 'target="_blank" rel="noopener noreferrer"'));

        // The description names nobody but the plugin: no credits line.
        self::assertStringNotContainsStringIgnoringCase('Originally created', $desc);
        self::assertStringNotContainsStringIgnoringCase('revisions by', $desc);

        // The guard the early-load test below depends on.
        self::assertStringContainsString(
            "if (function_exists('zen_is_superuser') && defined('SUPERUSER_PROFILE')) {",
            $src
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testManifestHidesReadMeFromANonSuperuser(): void
    {
        if (function_exists('zen_is_superuser') || defined('SUPERUSER_PROFILE')) {
            self::markTestSkipped('The admin is already loaded in this process.');
        }
        define('SUPERUSER_PROFILE', 1);
        function zen_is_superuser()
        {
            return false;
        }
        $manifest = require self::pluginDir() . '/manifest.php';
        $desc = $manifest['pluginDescription'];

        self::assertStringNotContainsString('>ReadMe</a>', $desc);
        self::assertStringNotContainsString('email_address_exporter_readme', $desc);
        self::assertStringContainsString('>GitHub</a>', $desc);
        self::assertStringContainsString('>Forum Support Thread</a>', $desc);
    }

    /**
     * Zen Cart 3.0.0 reads manifests early on every admin page, before
     * SUPERUSER_PROFILE exists; core's zen_is_superuser() then dies on the undefined
     * constant. The manifest must not call it there.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testManifestLoadsBeforeAdminAccessExists(): void
    {
        if (function_exists('zen_is_superuser') || defined('SUPERUSER_PROFILE')) {
            self::markTestSkipped('The admin is already loaded in this process.');
        }
        function zen_is_superuser()
        {
            return 1 === SUPERUSER_PROFILE;
        }
        $manifest = require self::pluginDir() . '/manifest.php';

        self::assertIsArray($manifest);
        self::assertStringContainsString('>ReadMe</a>', $manifest['pluginDescription']);
    }

    // ------------------------------------------------------------------ license lines

    public function testEveryPhpFileCarriesTheGplV2LicenseLine(): void
    {
        foreach (self::phpFiles() as $rel) {
            $src = (string)file_get_contents(self::pluginDir() . '/' . $rel);
            $licenseLines = preg_grep('~^\s*\*\s*@license\b~', preg_split('/\r\n|\n/', $src));

            self::assertSame([self::LICENSE_LINE], array_values($licenseLines), $rel);
        }
    }

    /**
     * This file is left out: its own patterns and messages have to name what they
     * look for.
     */
    public function testNoShippedFileStillNamesTheOldLicenseOrTheOldReadme(): void
    {
        foreach (self::phpFiles() as $rel) {
            if ($rel === 'tests/Unit/' . basename(__FILE__)) {
                continue;
            }
            $src = (string)file_get_contents(self::pluginDir() . '/' . $rel);

            self::assertDoesNotMatchRegularExpression('~zen-cart\.com/license|gpl-3\.0|GPL ?v?3(?![.\d])|Public License V3~i', $src, $rel);
            self::assertStringNotContainsStringIgnoringCase('readme.html', $src, $rel);
        }
    }
}
