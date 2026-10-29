<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('demo.admin_email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('DEMO_ADMIN_EMAIL must be a valid email address.');
        }

        // Never reset credentials, verify, or promote an existing identity.
        if (User::where('email', $email)->exists()) {
            $this->command?->info('Demo account already exists; left unchanged.');

            return;
        }

        $password = config('demo.admin_password');
        if (blank($password) && app()->environment('local', 'testing')) {
            $password = 'DemoAdmin!2026';
        }

        if (! is_string($password) || strlen($password) < 12
            || (! app()->environment('local', 'testing') && $password === 'DemoAdmin!2026')) {
            throw new InvalidArgumentException('Set DEMO_ADMIN_PASSWORD to a private password of at least 12 characters outside local/testing.');
        }

        $user = new User([
            'first_name' => 'Demo',
            'last_name' => 'Admin',
            'email' => $email,
            'password' => $password,
        ]);
        $user->email_verified_at = now();
        $user->save();

        // Organization membership and admin authorization belong to Phase 1.
        $this->command?->info('Demo identity created. Sign in at /login. Organization admin permissions are pending Phase 1.');
    }
}
