<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Services\WorkspaceProvisioner;
use Illuminate\Database\Seeder;

class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        if (Organization::where('slug', 'meridian-demo')->exists()) {
            $this->command?->info('Demo workspace already exists; memberships and permissions left unchanged.');

            return;
        }
        $owner = User::where('email', strtolower(trim(config('demo.admin_email'))))->firstOrFail();
        app(WorkspaceProvisioner::class)->bootstrap($owner, [
            'name' => 'Meridian Demo', 'slug' => 'meridian-demo', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $this->command?->info('Demo workspace created with the demo account as owner.');
    }
}
