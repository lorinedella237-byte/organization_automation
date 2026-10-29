<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['demo.admin_email' => 'admin@meridian.test', 'demo.admin_password' => null]);
    }

    public function test_default_seeding_creates_a_verified_demo_identity_with_hashed_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::sole();

        $this->assertSame('admin@meridian.test', $user->email);
        $this->assertSame('Demo Admin', $user->name);
        $this->assertTrue(Str::isUuid($user->uuid));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('DemoAdmin!2026', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_reseeding_preserves_existing_identity_and_changed_password(): void
    {
        $this->seed(AdminSeeder::class);
        $user = User::sole();
        $user->first_name = 'Updated';
        $user->password = 'MyChangedPassword!';
        $user->email_verified_at = null;
        $user->save();
        $before = $user->fresh()->getAttributes();

        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_regular_production_seeding_does_not_create_a_demo_account(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);
        $this->assertSame(0, User::count());
    }

    public function test_explicit_production_seeding_rejects_missing_or_public_demo_password(): void
    {
        $this->app->instance('env', 'production');
        foreach ([null, 'short', 'DemoAdmin!2026'] as $password) {
            config(['demo.admin_password' => $password]);
            try {
                $this->app->make(AdminSeeder::class)->run();
                $this->fail('Expected unsafe password to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('DEMO_ADMIN_PASSWORD', $exception->getMessage());
            }
            $this->assertSame(0, User::count());
        }
    }

    public function test_explicit_production_seeding_accepts_custom_credentials(): void
    {
        $this->app->instance('env', 'production');
        config(['demo.admin_email' => ' Demo@Example.test ', 'demo.admin_password' => 'PrivateDemoPassword!']);
        $this->artisan('db:seed', ['--class' => AdminSeeder::class, '--force' => true])->assertExitCode(0);

        $user = User::sole();
        $this->assertSame('demo@example.test', $user->email);
        $this->assertTrue(Hash::check('PrivateDemoPassword!', $user->password));
    }

    public function test_invalid_email_does_not_create_an_account(): void
    {
        config(['demo.admin_email' => 'invalid']);
        try {
            $this->seed(AdminSeeder::class);
            $this->fail('Expected invalid email to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('DEMO_ADMIN_EMAIL', $exception->getMessage());
        }
        $this->assertSame(0, User::count());
    }
}
