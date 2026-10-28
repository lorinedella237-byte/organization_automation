<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackendAudit extends Command
{
    protected $signature = 'lorine:backend-audit {--database : Read table names, row counts and migration history}';

    protected $description = 'Report backend readiness without changing schema or exposing credentials';

    public function handle(): int
    {
        $extensions = [];
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'] as $extension) {
            $extensions[$extension] = extension_loaded($extension);
        }

        $report = [
            'runtime' => [
                'php' => PHP_VERSION,
                'framework' => app()->version(),
                'target_php' => '>=8.2',
                'target_framework' => '12.x',
                'php_ready' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'framework_ready' => version_compare(app()->version(), '12.0.0', '>=')
                    && version_compare(app()->version(), '13.0.0', '<'),
                'extensions' => $extensions,
            ],
            'configuration' => [
                'app_key_present' => filled(config('app.key')),
                'database_driver' => config('database.default'),
                'queue_driver' => config('queue.default'),
                'mail_driver' => config('mail.default'),
                'session_driver' => config('session.driver'),
            ],
            'database' => ['status' => 'not_checked'],
        ];

        if ($this->option('database')) {
            $report['database'] = $this->inspectDatabase();
        }

        $report['ready'] = $report['runtime']['php_ready']
            && $report['runtime']['framework_ready']
            && ! in_array(false, $extensions, true)
            && $report['configuration']['app_key_present']
            && $report['database']['status'] === 'inspected';

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }

    private function inspectDatabase(): array
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if ($connection->getTablePrefix() !== '') {
                return ['status' => 'unsupported', 'reason' => 'Prefixed tables require a manual inventory.'];
            }

            if ($driver === 'mysql') {
                $tables = $connection->select(
                    'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ? AND table_type = ? ORDER BY table_name',
                    [$connection->getDatabaseName(), 'BASE TABLE']
                );
            } elseif ($driver === 'sqlite') {
                $tables = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            } else {
                return ['status' => 'unsupported', 'reason' => 'Use a manual read-only inventory for this driver.'];
            }

            $counts = [];
            foreach ($tables as $table) {
                $counts[$table->name] = $connection->table($table->name)->count();
            }

            $repository = config('database.migrations', 'migrations');
            $migrationTable = is_array($repository) ? $repository['table'] : $repository;
            $migrations = array_key_exists($migrationTable, $counts)
                ? $connection->table($migrationTable)->orderBy('id')->get(['migration', 'batch'])->all()
                : [];

            return [
                'status' => 'inspected',
                'tables' => $counts,
                'migrations' => $migrations,
                'data_present' => array_sum($counts) > 0,
                'backup_verified' => false,
                'note' => 'Read-only inventory, not a backup or permission to reset. Counts may change during concurrent writes.',
            ];
        } catch (Throwable $exception) {
            // Connection exceptions may contain credentials, hosts or SQL. Never print them.
            return [
                'status' => 'unavailable',
                'reason' => 'Database inventory failed. Verify connectivity and configuration privately; preserve all existing data.',
            ];
        }
    }
}
