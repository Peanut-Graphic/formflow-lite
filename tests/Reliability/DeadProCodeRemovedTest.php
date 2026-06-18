<?php
/**
 * Dead Pro-code removal (fix C).
 *
 * Five attribution-analytics methods referenced never-defined constants
 * (FFFL_TABLE_TOUCHES / HANDOFFS / VISITORS / EXTERNAL_COMPLETIONS) and had
 * zero callers anywhere in the plugin — so wiring or autoloading them would
 * be a hard fatal on PHP 8 ("Undefined constant"). They are Pro code that does
 * not belong in the free build and have been removed.
 *
 * Also asserts the never-defined constants are not referenced anywhere in the
 * shipped PHP, and that the dead `fffl_track_step` AJAX POST (to an action that
 * was never registered) is gone from the enrollment JS.
 *
 * @package FormFlow_Lite\Tests\Reliability
 */

namespace FFFL\Tests\Reliability;

use FFFL\Database\Database;
use PHPUnit\Framework\TestCase;

final class DeadProCodeRemovedTest extends TestCase
{
    private const REMOVED_METHODS = [
        'get_touch_summary',
        'get_handoff_stats',
        'get_visitor_journeys',
        'get_top_campaigns',
        'get_external_completions_summary',
    ];

    private const UNDEFINED_CONSTANTS = [
        'FFFL_TABLE_TOUCHES',
        'FFFL_TABLE_HANDOFFS',
        'FFFL_TABLE_VISITORS',
        'FFFL_TABLE_EXTERNAL_COMPLETIONS',
    ];

    public function testDeadProMethodsAreRemoved(): void
    {
        foreach (self::REMOVED_METHODS as $method) {
            $this->assertFalse(
                method_exists(Database::class, $method),
                "Dead Pro method Database::{$method}() should have been removed."
            );
        }
    }

    public function testNeverDefinedConstantsAreNotReferencedInPhp(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            $path = $file->getPathname();
            if (substr($path, -4) !== '.php') {
                continue;
            }
            if (strpos($path, '/vendor/') !== false
                || strpos($path, '/node_modules/') !== false
                || strpos($path, '/tests/') !== false) {
                continue;
            }
            $src = file_get_contents($path);
            foreach (self::UNDEFINED_CONSTANTS as $const) {
                if (strpos($src, $const) !== false) {
                    $offenders[] = basename($path) . ' references ' . $const;
                }
            }
        }

        $this->assertSame([], $offenders, "Never-defined Pro constants are still referenced:\n  " . implode("\n  ", $offenders));
    }

    public function testEnrollmentJsHasNoDeadTrackStepAjax(): void
    {
        $js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/enrollment.js');

        $this->assertStringNotContainsString(
            "action: 'fffl_track_step'",
            $js,
            'The dead fffl_track_step AJAX POST (unregistered action) must be removed.'
        );
    }
}
