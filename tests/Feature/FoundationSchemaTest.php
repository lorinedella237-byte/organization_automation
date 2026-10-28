<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_identity_factory_and_creation_match_schema_without_an_employer(): void
    {
        $user = User::create([
            'first_name' => 'Ada', 'middle_name' => 'M.', 'last_name' => 'Okafor',
            'email' => 'ada@example.test', 'password' => 'a-test-password',
        ])->fresh();
        $other = User::factory()->unverified()->create();

        $this->assertTrue(Str::isUuid($user->uuid));
        $this->assertNotSame($user->uuid, $other->uuid);
        $this->assertSame('Ada M. Okafor', $user->name);
        $this->assertSame($user->name, $user->toArray()['name']);
        $this->assertNull($user->employee_number);
        $this->assertNull($user->phone);
        $this->assertFalse($other->hasVerifiedEmail());
        $this->assertTrue(Hash::check('a-test-password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_uuid_uniqueness_is_enforced_by_database(): void
    {
        $first = User::factory()->create();
        $this->expectException(QueryException::class);
        User::factory()->create(['uuid' => $first->uuid]);
    }

    public function test_alignment_preserves_legacy_identity_permission_and_audit_records(): void
    {
        $migration = require database_path('migrations/2026_10_26_000002_align_identity_and_audit_schema.php');
        $migration->down();
        DB::table('users')->insert([
            'uuid' => (string) Str::uuid(), 'first_name' => 'Legacy', 'last_name' => 'User',
            'employee_number' => 'EMP-42', 'phone' => '0000000000',
            'email' => 'legacy@example.test', 'password' => 'historical-hash',
        ]);
        DB::table('audit_logs')->insert([
            'uuid' => (string) Str::uuid(), 'event_type' => 'test',
            'module' => 'test', 'action' => 'test',
        ]);
        DB::table('permission')->insert([
            'code' => 'staff.view', 'module' => 'staff', 'action' => 'view',
            'resource' => 'staff', 'name' => 'View staff',
        ]);

        $migration->up();

        $this->assertSame('EMP-42', DB::table('users')->value('employee_number'));
        $this->assertSame('historical-hash', DB::table('users')->value('password'));
        $this->assertSame('staff.view', DB::table('permissions')->value('code'));
        $this->assertNull(DB::table('audit_logs')->value('created_at'));
        $this->assertFalse(Schema::hasTable('permission'));
    }

    public function test_unsafe_legacy_rollback_is_refused_before_any_schema_change(): void
    {
        User::factory()->create();
        $migration = require database_path('migrations/2026_10_26_000002_align_identity_and_audit_schema.php');

        try {
            $migration->down();
            $this->fail('Rollback must reject identities with null legacy fields.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Cannot restore required legacy', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'created_at'));
        $this->assertSame(1, User::count());
    }

    public function test_all_migrations_roll_back_and_reapply_on_disposable_database(): void
    {
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);
        foreach (['users', 'permission', 'permissions', 'roles', 'audit_logs', 'mail_notifications', 'social_media_auth', 'jobs', 'job_batches'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('permissions'));
    }
}
