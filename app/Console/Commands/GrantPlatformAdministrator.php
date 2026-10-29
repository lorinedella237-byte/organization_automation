<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantPlatformAdministrator extends Command
{
    protected $signature = 'platform:grant {email : Existing verified account to authorize}';
    protected $description = 'Authorize an existing account for platform organization provisioning only';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->whereNotNull('email_verified_at')->first();
        if (! $user) {
            $this->error('An existing verified account is required.');

            return self::FAILURE;
        }
        DB::table('platform_administrators')->insertOrIgnore(['user_id' => $user->id, 'created_at' => now()]);
        $this->info('Platform provisioning enabled. No workspace access was granted.');

        return self::SUCCESS;
    }
}
