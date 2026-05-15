<?php
/**
 * Regression guard: ABSPATH guard must never precede a file's `namespace`.
 *
 * Incident (2026-05-15): an automated security-hardening pass prepended a
 * WordPress direct-access guard ABOVE the `namespace ...;` line in namespaced
 * PHP files. PHP requires the namespace declaration to be the very first
 * statement (after an optional `declare`), so every affected file fataled
 * with: "Namespace declaration statement has to be the very first
 * statement...".
 *
 * Sibling of formflow#3 / peanut-suite. This test is intentionally
 * SELF-CONTAINED: it performs a filesystem-only scan and does NOT load the
 * WordPress-coupled tests/bootstrap.php, so it remains runnable even when the
 * plugin tree is broken.
 *
 * @package FormFlow_Lite\Tests\Regression
 */

namespace FFFL\Tests\Regression;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class AbspathGuardOrderTest extends PHPUnitTestCase
{
    /**
     * Recursively scan every *.php file in the repo (excluding vendor,
     * node_modules, .claude, .git) and assert no file has an ABSPATH guard
     * occurring before its first `namespace` declaration.
     */
    public function testNoAbspathGuardBeforeNamespace(): void
    {
        $root = dirname(__DIR__, 2);

        $excludedDirs = ['vendor', 'node_modules', '.claude', '.git'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $current) use ($excludedDirs): bool {
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), $excludedDirs, true);
                    }
                    return $current->getExtension() === 'php';
                }
            )
        );

        $offenders = [];

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            $namespaceLine = null;
            $guardLine = null;

            foreach ($lines as $idx => $line) {
                if ($namespaceLine === null
                    && preg_match('/^\s*namespace\s+[A-Za-z_\\\\]/', $line)
                ) {
                    $namespaceLine = $idx + 1;
                }
                // Match the actual direct-access guard idiom
                //   if ( ! defined( 'ABSPATH' ) ) { exit; }
                // (a negated defined() check), not a bare mention of the
                // constant in prose/comments. Robust to spacing + quote style.
                if ($guardLine === null
                    && preg_match(
                        '/!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)/',
                        $line
                    )
                ) {
                    $guardLine = $idx + 1;
                }
            }

            // Only files that have BOTH a namespace and a guard can be broken.
            if ($namespaceLine !== null
                && $guardLine !== null
                && $guardLine < $namespaceLine
            ) {
                $relative = ltrim(str_replace($root, '', $path), '/\\');
                $offenders[] = sprintf(
                    '%s: ABSPATH guard at line %d precedes namespace at line %d',
                    $relative,
                    $guardLine,
                    $namespaceLine
                );
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            sprintf(
                "%d file(s) have an ABSPATH guard before their namespace "
                . "declaration (PHP fatal). Offenders:\n%s",
                count($offenders),
                implode("\n", $offenders)
            )
        );
    }
}
