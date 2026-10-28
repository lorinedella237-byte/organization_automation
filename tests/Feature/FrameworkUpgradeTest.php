<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrameworkUpgradeTest extends TestCase
{
    public function test_existing_web_routes_boot_on_laravel_twelve(): void
    {
        $this->assertStringStartsWith('12.', app()->version());
        $this->withoutVite()->get('/')->assertOk()->assertViewIs('landing');
        $this->get('/home')->assertOk()->assertSee('welcome simon');
    }

    public function test_sanctum_protected_route_rejects_anonymous_json_requests(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_framework_cors_middleware_handles_preflight(): void
    {
        $this->options('/api/user', [], [
            'Origin' => 'https://example.test',
            'Access-Control-Request-Method' => 'GET',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
