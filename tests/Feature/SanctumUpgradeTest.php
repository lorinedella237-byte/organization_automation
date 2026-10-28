<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SanctumUpgradeTest extends TestCase
{
    public function test_additive_token_migration_preserves_existing_tokens_and_supports_expiration(): void
    {
        $original = require database_path('migrations/2019_12_14_000001_create_personal_access_tokens_table.php');
        $original->up();
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => User::class,
            'tokenable_id' => 123,
            'name' => 'existing token',
            'token' => hash('sha256', 'existing-test-token'),
        ]);

        $upgrade = require database_path('migrations/2026_10_26_000001_add_expires_at_to_personal_access_tokens_table.php');
        $upgrade->up();

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->assertNull(DB::table('personal_access_tokens')->value('expires_at'));

        $user = new User();
        $user->id = 123;
        $expiry = now()->addHour()->startOfSecond();
        $token = $user->createToken('upgrade test', ['read'], $expiry);
        $this->assertTrue($token->accessToken->fresh()->expires_at->equalTo($expiry));

        $upgrade->down();
        $this->assertFalse(Schema::hasColumn('personal_access_tokens', 'expires_at'));
        $this->assertSame(2, DB::table('personal_access_tokens')->count());
    }
}
