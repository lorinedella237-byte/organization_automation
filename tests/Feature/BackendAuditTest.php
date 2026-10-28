<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackendAuditTest extends TestCase
{
    public function test_default_test_database_is_isolated(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_database_is_not_opened_without_explicit_option(): void
    {
        DB::shouldReceive('connection')->never();

        Artisan::call('lorine:backend-audit');
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('not_checked', $report['database']['status']);
        $this->assertFalse($report['ready']);
        $this->assertSame('>=8.2', $report['runtime']['target_php']);
        $this->assertSame('12.x', $report['runtime']['target_framework']);
    }

    public function test_inventory_counts_records_without_exposing_or_changing_them(): void
    {
        Schema::create('audit_probe', function ($table) {
            $table->id();
            $table->string('private_value');
        });
        Schema::create('migrations', function ($table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
        DB::table('audit_probe')->insert(['private_value' => 'never-print-this-value']);
        DB::table('migrations')->insert(['migration' => 'example_migration', 'batch' => 1]);

        Artisan::call('lorine:backend-audit', ['--database' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('inspected', $report['database']['status']);
        $this->assertSame(1, $report['database']['tables']['audit_probe']);
        $this->assertSame('example_migration', $report['database']['migrations'][0]['migration']);
        $this->assertTrue($report['database']['data_present']);
        $this->assertFalse($report['database']['backup_verified']);
        $this->assertStringNotContainsString('never-print-this-value', $output);
        $this->assertSame('never-print-this-value', DB::table('audit_probe')->value('private_value'));
    }

    public function test_connection_failures_are_redacted_and_not_reported_as_empty_databases(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new \RuntimeException('secret-password database-host private-sql'));

        $exit = Artisan::call('lorine:backend-audit', ['--database' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exit);
        $this->assertSame('unavailable', $report['database']['status']);
        $this->assertArrayNotHasKey('data_present', $report['database']);
        $this->assertStringNotContainsString('secret-password', $output);
        $this->assertStringNotContainsString('database-host', $output);
        $this->assertStringNotContainsString('private-sql', $output);
    }
}
