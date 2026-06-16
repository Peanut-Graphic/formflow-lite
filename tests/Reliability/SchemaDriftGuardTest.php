<?php
/**
 * Schema-drift guard (CI net #3).
 *
 * Statically asserts that every column written via `$wpdb->insert()` /
 * `$wpdb->update()` to one of the 7 real `fffl_*` tables that the plugin
 * actually CREATES exists in that table's dbDelta `CREATE TABLE` schema.
 *
 * This catches the class of bug where code writes a column the schema never
 * defines (or a schema bump never reaches an existing install) — i.e. the
 * "Unknown column" production failures the upgrade/self-heal mechanism is
 * meant to prevent.
 *
 * The Pro-feature tables (audit_log, scheduled_reports, gdpr_requests, the
 * attribution/analytics tables) are intentionally NOT created in the Lite
 * build and are guarded at runtime by Database::table_exists(); they are
 * deliberately excluded here.
 *
 * @package FormFlow_Lite\Tests\Reliability
 */

namespace FFFL\Tests\Reliability;

use PHPUnit\Framework\TestCase;

final class SchemaDriftGuardTest extends TestCase
{
    /** The 7 real tables the plugin creates (bare names, no prefix). */
    private const REAL_TABLES = [
        'fffl_instances',
        'fffl_submissions',
        'fffl_logs',
        'fffl_retry_queue',
        'fffl_webhooks',
        'fffl_api_usage',
        'fffl_resume_tokens',
    ];

    private static function activatorSource(): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/includes/class-activator.php');
    }

    private static function databaseSource(): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/includes/database/class-database.php');
    }

    /**
     * Parse every `CREATE TABLE` block in the Activator and return
     * [ table_name => [col, col, ...] ] for the real tables only.
     *
     * @return array<string,string[]>
     */
    private function schemaColumns(): array
    {
        $src = self::activatorSource();
        $constMap = $this->tableConstantMap();
        $schemas = [];

        // Match: $sql_x = "CREATE TABLE {$table_x} ( ... ) ...";
        if (!preg_match_all('/CREATE TABLE\s+\{\$(\w+)\}\s*\((.*?)\)\s*\{\$charset_collate\}/s', $src, $blocks, PREG_SET_ORDER)) {
            $this->fail('Could not parse any CREATE TABLE blocks from the Activator.');
        }

        // Resolve the local $table_x variable -> table name by reading its
        // assignment (e.g. $table_instances = $wpdb->prefix . FFFL_TABLE_INSTANCES;
        // or $table_retry_queue = $wpdb->prefix . 'fffl_retry_queue';).
        foreach ($blocks as $b) {
            $varName = $b[1];
            $body    = $b[2];
            $table   = $this->resolveTableVar($src, $varName, $constMap);
            if ($table === null || !in_array($table, self::REAL_TABLES, true)) {
                continue;
            }
            $schemas[$table] = $this->parseColumns($body);
        }

        return $schemas;
    }

    /** Map FFFL_TABLE_* constant name -> bare table name. */
    private function tableConstantMap(): array
    {
        return [
            'FFFL_TABLE_INSTANCES'     => 'fffl_instances',
            'FFFL_TABLE_SUBMISSIONS'   => 'fffl_submissions',
            'FFFL_TABLE_LOGS'          => 'fffl_logs',
            'FFFL_TABLE_WEBHOOKS'      => 'fffl_webhooks',
            'FFFL_TABLE_API_USAGE'     => 'fffl_api_usage',
            'FFFL_TABLE_RESUME_TOKENS' => 'fffl_resume_tokens',
        ];
    }

    private function resolveTableVar(string $src, string $varName, array $constMap): ?string
    {
        if (!preg_match('/\$' . preg_quote($varName, '/') . '\s*=\s*\$wpdb->prefix\s*\.\s*([^;]+);/', $src, $m)) {
            return null;
        }
        $rhs = trim($m[1]);
        // Quoted literal: 'fffl_retry_queue'
        if (preg_match("/^['\"]([\w]+)['\"]$/", $rhs, $q)) {
            return $q[1];
        }
        // Constant: FFFL_TABLE_INSTANCES
        if (isset($constMap[$rhs])) {
            return $constMap[$rhs];
        }
        return null;
    }

    /** Extract column names from a CREATE TABLE body. */
    private function parseColumns(string $body): array
    {
        $cols = [];
        foreach (preg_split('/,\s*\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Skip index / key / constraint lines.
            if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY|INDEX|CONSTRAINT|FOREIGN KEY)\b/i', $line)) {
                continue;
            }
            if (preg_match('/^`?(\w+)`?\s+/', $line, $m)) {
                $cols[] = strtolower($m[1]);
            }
        }
        return $cols;
    }

    /**
     * Find every insert()/update() call against a known real-table variable in
     * the Database class and return [ table => [ written_columns ] ].
     *
     * @return array<string,string[]>
     */
    private function writtenColumns(): array
    {
        $src = self::databaseSource();

        // Map the Database class member vars to bare table names.
        $varToTable = [
            'this->table_instances'   => 'fffl_instances',
            'this->table_submissions' => 'fffl_submissions',
            'this->table_logs'        => 'fffl_logs',
            // local $table for retry_queue / webhooks / api_usage / resume_tokens
        ];
        // Also resolve local `$table = $this->wpdb->prefix . 'fffl_xxx';`
        // assignments by scanning method-by-method below.

        $written = [];

        // Precompute every `$table = $this->wpdb->prefix . 'fffl_xxx';`
        // assignment with its byte offset so a local `$table` insert/update can
        // be resolved to the nearest *preceding* assignment.
        $localAssigns = [];
        if (preg_match_all("/\\\$table\s*=\s*\\\$this->wpdb->prefix\s*\.\s*'([\w]+)'/", $src, $am, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($am as $a) {
                $localAssigns[] = ['offset' => $a[0][1], 'table' => $a[1][0]];
            }
        }

        // insert( <target>, [ ... ] )  and  update( <target>, [ ... ], ... )
        // We match the target token and the immediately-following array literal.
        $pattern = '/\$this->wpdb->(insert|update)\(\s*([^,]+?)\s*,\s*\[(.*?)\]/s';
        if (!preg_match_all($pattern, $src, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $written;
        }

        foreach ($calls as $c) {
            $target = trim($c[2][0]);
            $arrayBody = $c[3][0];
            $callOffset = $c[0][1];

            $table = $this->resolveWriteTarget($target, $localAssigns, $callOffset);
            if ($table === null || !in_array($table, self::REAL_TABLES, true)) {
                continue;
            }

            foreach ($this->parseArrayKeys($arrayBody) as $key) {
                $written[$table][] = $key;
            }
        }

        return $written;
    }

    /** Resolve an insert/update target token to a bare real-table name, if known. */
    private function resolveWriteTarget(string $target, array $localAssigns, int $callOffset): ?string
    {
        $target = ltrim($target, '$');

        $members = [
            'this->table_instances'   => 'fffl_instances',
            'this->table_submissions' => 'fffl_submissions',
            'this->table_logs'        => 'fffl_logs',
        ];
        if (isset($members[$target])) {
            return $members[$target];
        }

        // Local `$table` — find the nearest preceding assignment.
        if ($target === 'table') {
            $best = null;
            foreach ($localAssigns as $a) {
                if ($a['offset'] < $callOffset) {
                    $best = $a['table'];
                } else {
                    break;
                }
            }
            return $best;
        }

        return null;
    }

    private function parseArrayKeys(string $arrayBody): array
    {
        $keys = [];
        if (preg_match_all("/['\"]([\w]+)['\"]\s*=>/", $arrayBody, $m)) {
            foreach ($m[1] as $k) {
                $keys[] = strtolower($k);
            }
        }
        return $keys;
    }

    public function testSchemaParsesAllSevenRealTables(): void
    {
        $schemas = $this->schemaColumns();
        foreach (self::REAL_TABLES as $t) {
            $this->assertArrayHasKey($t, $schemas, "CREATE TABLE for {$t} not found / not parsed.");
            $this->assertNotEmpty($schemas[$t], "No columns parsed for {$t}.");
            $this->assertContains('id', $schemas[$t], "Expected an id column in {$t}.");
        }
    }

    public function testEveryWrittenColumnExistsInSchema(): void
    {
        $schemas = $this->schemaColumns();
        $written = $this->writtenColumns();

        $this->assertNotEmpty($written, 'Expected to find at least one insert/update against a real table.');

        $drift = [];
        foreach ($written as $table => $cols) {
            $schemaCols = $schemas[$table] ?? [];
            foreach (array_unique($cols) as $col) {
                if (!in_array($col, $schemaCols, true)) {
                    $drift[] = "{$table}.{$col}";
                }
            }
        }

        $this->assertSame(
            [],
            $drift,
            "Columns written by Database but missing from the dbDelta schema (drift):\n  "
            . implode("\n  ", $drift)
        );
    }
}
