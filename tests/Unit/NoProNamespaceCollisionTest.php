<?php
/**
 * Lite must not squat on FormFlow Pro's global namespace.
 *
 * Found 2026-07-24 on peanutgraphic.com with both plugins active:
 *
 *     Warning: Constant ISF_INTELLISOURCE_PATH already defined
 *
 * Both plugins did `define('ISF_INTELLISOURCE_PATH', __DIR__)` UNGUARDED.
 * Whichever loaded second lost — the constant kept the FIRST plugin's path, so
 * Lite's loader would `require_once` PRO's connector files. Those declare
 * `ISF\...` classes, not `FFFL\...`, so Lite's own connector would never be
 * defined. Confirmed live: the constant resolved to
 * .../plugins/formflow/connectors/intellisource while Lite was active.
 *
 * A `!defined()` guard would be the WRONG fix — it silences the warning while
 * leaving Lite pointed at Pro's directory. Lite simply must not use an `ISF_`
 * constant: that prefix is Pro's, and this one is a leftover from the
 * Pro -> Lite port. Lite owns the `FFFL_` prefix.
 *
 * Consequence beyond the warning: PHP 9 makes redefining a constant a fatal.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class NoProNamespaceCollisionTest extends TestCase
{
    /**
     * Every PHP file Lite ships, excluding vendor and tests.
     *
     * @return string[]
     */
    private function pluginPhpFiles(): array
    {
        $root = rtrim(FFFL_PLUGIN_DIR, '/');
        $files = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            $path = $file->getPathname();
            if (substr($path, -4) !== '.php') {
                continue;
            }

            // Match RELATIVE to the plugin root. Matching the absolute path is a
            // trap: this plugin's own worktrees live under .claude/worktrees/, so
            // an absolute "/.claude/" exclusion silently skips every file and the
            // test passes while scanning nothing.
            $rel = ltrim(substr($path, strlen($root)), '/');
            if (strpos($rel, 'vendor/') === 0
                || strpos($rel, 'tests/') === 0
                || strpos($rel, 'node_modules/') === 0
                || strpos($rel, '.claude/') === 0
            ) {
                continue;
            }
            $files[] = $path;
        }

        // A scan of nothing must fail loudly rather than pass vacuously.
        $this->assertNotEmpty($files, 'Scanned no plugin PHP files — this guard would pass vacuously.');

        return $files;
    }

    /**
     * The specific collision, and any sibling of it.
     */
    public function test_lite_defines_no_isf_prefixed_constants(): void
    {
        $offenders = [];

        foreach ($this->pluginPhpFiles() as $path) {
            $src = (string) file_get_contents($path);
            if (preg_match_all("/define\(\s*'(ISF_[A-Z0-9_]+)'/", $src, $m)) {
                foreach ($m[1] as $const) {
                    $offenders[] = basename($path) . ' -> ' . $const;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "FormFlow Lite defines constants in FormFlow Pro's ISF_ namespace: "
            . implode(', ', $offenders) . '. With both plugins active the second '
            . 'to load silently inherits the first plugin\'s path. Use the FFFL_ prefix.'
        );
    }

    /**
     * The connector loader must still resolve its own directory — the point is
     * to rename the constant, not to break the requires that use it.
     */
    public function test_intellisource_loader_resolves_its_own_directory(): void
    {
        $loader = FFFL_PLUGIN_DIR . 'connectors/intellisource/loader.php';
        $this->assertFileExists($loader);

        $src = (string) file_get_contents($loader);

        $this->assertMatchesRegularExpression(
            "/define\(\s*'FFFL_INTELLISOURCE_PATH'\s*,\s*__DIR__\s*\)/",
            $src,
            'The loader must define its path under the FFFL_ prefix, pointing at its own __DIR__.'
        );

        // Every require in this file must go through the Lite-owned constant.
        $this->assertDoesNotMatchRegularExpression(
            '/require_once\s+ISF_INTELLISOURCE_PATH/',
            $src,
            'A require still resolves through the Pro-namespaced constant, so Lite could '
            . 'load Pro\'s connector classes instead of its own.'
        );
        $this->assertMatchesRegularExpression(
            '/require_once\s+FFFL_INTELLISOURCE_PATH/',
            $src,
            'The loader requires must resolve through the Lite-owned constant.'
        );
    }

    /**
     * Guard the whole class of problem: Lite must not reference any ISF_
     * constant it does not own, or it is reading Pro's state.
     */
    public function test_lite_references_no_isf_constants_at_all(): void
    {
        $offenders = [];

        foreach ($this->pluginPhpFiles() as $path) {
            $src = (string) file_get_contents($path);
            // Bare constant usage, e.g. `ISF_FOO . '/x.php'` — not strings.
            if (preg_match_all('/(?<![\'"$\w])(ISF_[A-Z0-9_]{2,})\b/', $src, $m)) {
                foreach (array_unique($m[1]) as $const) {
                    $offenders[] = basename($path) . ' -> ' . $const;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'FormFlow Lite reads FormFlow Pro constants: ' . implode(', ', $offenders)
            . '. Lite must never depend on Pro being installed or on its values.'
        );
    }
}
