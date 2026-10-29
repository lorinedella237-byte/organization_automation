<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    private Organization $organization;
    private Organization $other;
    private OrganizationMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), ['name' => 'First', 'slug' => 'first', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
        $this->other = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), ['name' => 'Second', 'slug' => 'second', 'timezone' => 'UTC', 'currency' => 'USD']);
        $this->owner = $this->organization->memberships()->sole();
    }

    private function member(string $role = 'staff', ?User $user = null): OrganizationMembership
    {
        $member = OrganizationMembership::create(['organization_id' => $this->organization->id, 'user_id' => ($user ?? User::factory()->create())->id]);
        $roleId = Role::where('organization_id', $this->organization->id)->where('code', $role)->value('id');
        app(RoleService::class)->assign($this->owner, $member, [$roleId]);

        return $member;
    }

    private function signIn(OrganizationMembership $member): void
    {
        $this->actingAs($member->user)->withSession([
            'current_organization_id' => $member->organization_id,
            'password_hash_web' => $member->user->getAuthPassword(),
        ]);
    }

    public function test_tenant_boundaries_cover_detail_search_writes_and_workspace_switch(): void
    {
        $this->signIn($this->owner);
        $foreign = $this->other->memberships()->sole();
        $this->getJson('/workspace/staff')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->owner->id);
        $this->getJson('/workspace/staff?search='.urlencode($foreign->user->email))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/workspace/staff/'.$foreign->id)->assertNotFound();
        $this->patchJson('/workspace/staff/'.$foreign->id, ['title' => 'leak', 'reason' => 'test'])->assertNotFound();
        $this->postJson('/workspace/switch', ['organization' => $this->other->id])->assertNotFound();
        $this->assertSame($this->organization->id, session('current_organization_id'));
    }

    public function test_direct_report_and_department_scope_do_not_leak_other_staff_to_html_or_json(): void
    {
        $manager = $this->member('manager');
        $report = $this->member();
        $unrelated = $this->member();
        $report->update(['manager_membership_id' => $manager->id]);
        $this->signIn($manager);
        $this->getJson('/workspace/staff')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $report->id);
        $this->getJson('/workspace/staff/'.$unrelated->id)->assertNotFound();
        $this->get('/workspace/staff/'.$report->id)->assertOk()->assertViewHas('managers', fn ($rows) => $rows->isEmpty());
        $this->signIn($report);
        $this->getJson('/workspace/staff')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $report->id);
        $this->get('/workspace/staff/'.$report->id)->assertViewHas('roles', fn ($rows) => $rows->isEmpty());
    }

    public function test_admin_cannot_access_finance_or_delegate_finance_to_self(): void
    {
        $admin = $this->member('organization_admin');
        $this->assertFalse(app(WorkspaceAccess::class)->allows($admin, 'payroll.prepare'));
        $this->signIn($admin);
        $finance = Role::where('organization_id', $this->organization->id)->where('code', 'finance_officer')->value('id');
        $this->putJson('/workspace/staff/'.$admin->id.'/roles', ['role_ids' => [$finance]])->assertForbidden();
        $this->postJson('/workspace/roles', ['name' => 'Escalation', 'grants' => [['ability' => 'payroll.prepare', 'scope' => 'organization']]])->assertForbidden();
        $this->assertFalse(app(WorkspaceAccess::class)->allows($admin, 'payroll.prepare'));
    }

    public function test_suspension_and_organization_suspension_revoke_existing_session_access(): void
    {
        $member = $this->member();
        $this->signIn($this->owner);
        $this->postJson('/workspace/staff/'.$member->id.'/status', ['status' => 'suspended', 'reason' => 'Access review'])->assertOk();
        $this->signIn($member);
        $this->getJson('/workspace/staff')->assertForbidden();
        $this->assertSame([], app(WorkspaceAccess::class)->scopes($member, 'staff.view'));
        $this->signIn($this->owner);
        $this->organization->update(['status' => 'suspended']);
        $this->getJson('/workspace/settings')->assertForbidden();
    }

    public function test_switch_uses_new_workspace_permissions_and_recovers_from_revoked_selection(): void
    {
        $member = $this->member('staff', $this->other->memberships()->sole()->user);
        $this->signIn($member);
        $this->getJson('/workspace/settings')->assertForbidden();
        $this->postJson('/workspace/switch', ['organization' => $this->other->id])->assertOk();
        $this->getJson('/workspace/settings')->assertOk()->assertJsonPath('data.id', $this->other->id);
        $this->other->update(['status' => 'suspended']);
        $this->getJson('/workspace/settings')->assertForbidden();
        $this->postJson('/workspace/switch', ['organization' => $this->organization->id])->assertOk();
        $this->getJson('/workspace/settings')->assertForbidden();
    }

    public function test_manager_cycles_and_cross_tenant_assignments_are_rejected(): void
    {
        $first = $this->member();
        $second = $this->member();
        $department = Department::create(['organization_id' => $this->other->id, 'name' => 'Private']);
        $this->signIn($this->owner);
        $this->patchJson('/workspace/staff/'.$first->id, ['manager_membership_id' => $second->id, 'reason' => 'Reporting line'])->assertOk();
        $this->patchJson('/workspace/staff/'.$second->id, ['manager_membership_id' => $first->id, 'reason' => 'Cycle'])->assertUnprocessable();
        $this->patchJson('/workspace/staff/'.$first->id, ['department_id' => $department->id, 'reason' => 'Foreign'])->assertUnprocessable();
        $this->assertNull($first->fresh()->department_id);
    }

    public function test_database_foreign_keys_reject_cross_tenant_membership_role(): void
    {
        $foreignRole = Role::where('organization_id', $this->other->id)->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('membership_roles')->insert(['organization_id' => $this->organization->id, 'organization_membership_id' => $this->owner->id, 'role_id' => $foreignRole->id, 'created_at' => now()]);
    }

    public function test_last_owner_and_system_templates_are_protected_and_changes_audited(): void
    {
        $this->signIn($this->owner);
        $role = Role::where('organization_id', $this->organization->id)->where('code', 'owner')->firstOrFail();
        $this->putJson('/workspace/roles/'.$role->id, ['name' => 'Changed', 'grants' => []])->assertForbidden();
        $this->putJson('/workspace/staff/'.$this->owner->id.'/roles', ['role_ids' => []])->assertUnprocessable();
        $this->postJson('/workspace/staff/'.$this->owner->id.'/status', ['status' => 'offboarded', 'reason' => 'Last owner'])->assertUnprocessable();
        $this->patchJson('/workspace/settings', ['name' => 'Renamed', 'organization_id' => $this->other->id, 'reason' => 'Approved rename'])->assertOk();
        $this->assertSame('Renamed', $this->organization->fresh()->name);
        $this->assertSame('Second', $this->other->fresh()->name);
        $this->getJson('/workspace/audit')->assertOk()->assertJsonPath('data.0.action', 'organization.updated');
        $this->assertStringNotContainsString('password', DB::table('audit_logs')->orderByDesc('id')->value('metadata'));
    }

    public function test_custom_department_scope_requires_same_non_null_department(): void
    {
        $member = $this->member();
        $otherMember = $this->member();
        $department = Department::create(['organization_id' => $this->organization->id, 'name' => 'Operations']);
        $role = app(RoleService::class)->save($this->owner, ['name' => 'Department reader', 'grants' => [['ability' => 'staff.view', 'scope' => 'department']]]);
        app(RoleService::class)->assign($this->owner, $member, [$role->id]);
        $this->assertFalse(app(WorkspaceAccess::class)->allows($member, 'staff.view', $otherMember));
        $member->update(['department_id' => $department->id]);
        $otherMember->update(['department_id' => $department->id]);
        $this->assertTrue(app(WorkspaceAccess::class)->allows($member, 'staff.view', $otherMember));
        $this->signIn($member);
        $this->getJson('/workspace/staff')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_native_html_forms_redirect_after_writes_and_accept_empty_custom_role(): void
    {
        $this->signIn($this->owner);
        $this->post('/workspace/roles', ['name' => 'Empty custom role', 'grants' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $role = Role::where('name', 'Empty custom role')->sole();
        $this->assertFalse($role->is_system_role);
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $role->id)->count());
        $this->post('/workspace/departments', ['name' => 'Finance'])->assertRedirect(route('departments.index'));
        $this->patch('/workspace/settings', ['name' => 'Updated company', 'reason' => 'Approved name change'])->assertRedirect(route('settings.organization'));
        $member = $this->member();
        $this->patch('/workspace/staff/'.$member->id, ['title' => 'Analyst', 'reason' => 'New role'])->assertRedirect(route('staff.show', $member));
        $this->assertSame('Analyst', $member->fresh()->title);
    }

    public function test_personal_template_upgrade_preserves_broader_grants_and_is_repeatable(): void
    {
        $adminRole = Role::where('organization_id', $this->organization->id)->where('code', 'organization_admin')->sole();
        $payslip = DB::table('permissions')->where('code', 'payslips.view')->value('id');
        DB::table('role_permissions')->where('role_id', $adminRole->id)->where('permission_id', $payslip)->delete();
        $migration = require database_path('migrations/2026_10_29_000002_add_personal_permissions_to_system_roles.php');
        $migration->up();
        $migration->up();
        $this->assertSame(1, DB::table('role_permissions')->where('role_id', $adminRole->id)->where('permission_id', $payslip)->count());
        $this->assertSame('own', DB::table('role_permissions')->where('role_id', $adminRole->id)->where('permission_id', $payslip)->value('scope'));
        $admin = $this->member('organization_admin');
        $this->assertFalse(app(WorkspaceAccess::class)->allows($admin, 'payroll.prepare'));
        $this->assertTrue(app(WorkspaceAccess::class)->allows($admin, 'staff.view'));
    }
}
