<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\WorkspaceProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The topbar.
 *
 * Its two branches had no coverage: a reader who belongs to one organization
 * and a reader who belongs to several see different controls, and only the
 * second one can switch. The unread count is asserted through the control's
 * accessible name as well as its chip, because the chip is hidden from
 * assistive technology to keep the number from being read twice.
 */
class TopbarRenderTest extends TestCase
{
    private Organization $organization;

    private OrganizationMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), [
            'name' => 'Harbour Authority', 'slug' => 'harbour', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $this->owner = $this->organization->memberships()->sole();
    }

    public function test_one_workspace_is_named_but_offers_nothing_to_switch_to(): void
    {
        $response = $this->actingAs($this->owner->user)->get('/home')->assertOk();

        $response->assertSee('Harbour Authority');
        $response->assertDontSee(route('workspace.switch'), false);
        $response->assertDontSee('Current workspace');
    }

    public function test_several_workspaces_become_a_menu_that_marks_the_current_one(): void
    {
        app(WorkspaceProvisioner::class)->bootstrap($this->owner->user, [
            'name' => 'Northwind Trial', 'slug' => 'northwind', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);

        $response = $this->actingAs($this->owner->user)->get('/home')->assertOk();

        $response->assertSee('Harbour Authority');
        $response->assertSee('Northwind Trial');
        $response->assertSee(route('workspace.switch'), false);
        // The checked item is named for assistive technology, not drawn only.
        $response->assertSee('Current workspace');
    }

    public function test_an_unread_count_is_shown_once_and_named_in_words(): void
    {
        DB::table('workspace_notifications')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organization->id,
            'organization_membership_id' => $this->owner->id,
            'event_key' => 'report.returned',
            'message' => 'Your weekly report was returned.',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->owner->user)->get('/home')->assertOk();

        $response->assertSee('Notifications, 1 unread');
        $response->assertSee('app-bar-count', false);
    }

    public function test_nothing_unread_draws_no_chip(): void
    {
        $response = $this->actingAs($this->owner->user)->get('/home')->assertOk();

        $response->assertSee('Notifications, none unread');
        $response->assertDontSee('app-bar-count', false);
    }

    public function test_the_appearance_control_keeps_one_name_and_reports_its_state(): void
    {
        $response = $this->actingAs($this->owner->user)->get('/home')->assertOk();

        // A toggle button: the name stays put and aria-pressed carries the
        // answer, so the glyph is free to show the appearance in force.
        $response->assertSee('aria-pressed="false"', false);
        $response->assertSee('Dark appearance');
    }
}
