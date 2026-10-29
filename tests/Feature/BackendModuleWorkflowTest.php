<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Compensation;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\ProjectService;
use App\Services\WorkspaceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackendModuleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), ['name' => 'Workflow', 'slug' => 'workflow', 'timezone' => 'UTC', 'currency' => 'NGN']);
        $this->owner = $organization->memberships()->sole();
    }

    public function test_project_task_comment_checklist_and_activity_workflows_are_tenant_scoped(): void
    {
        $project = app(ProjectService::class)->create($this->owner, ['name' => 'Pilot', 'code' => 'PILOT']);
        $task = app(ProjectService::class)->createTask($this->owner, $project, ['title' => 'Prepare launch']);
        $comment = app(ProjectService::class)->addTaskComment($this->owner, $task, 'Ready for review.');
        $item = app(ProjectService::class)->saveChecklistItem($this->owner, $task, ['label' => 'Review', 'completed' => true]);
        $activity = app(ProjectService::class)->saveActivity($this->owner, ['activity_date' => '2026-10-29', 'title' => 'Planning', 'project_id' => $project->id]);

        $this->assertSame('Ready for review.', $comment->body);
        $this->assertTrue($item->completed);
        $this->assertSame($this->owner->id, $activity->membership_id);
        $this->assertDatabaseHas('task_history', ['task_id' => $task->id, 'event' => 'created']);
    }

    public function test_project_lifecycle_transitions_are_validated_and_history_is_retained(): void
    {
        $project = app(ProjectService::class)->create($this->owner, ['name' => 'Lifecycle']);

        app(ProjectService::class)->update($this->owner, $project, ['status' => 'active']);
        app(ProjectService::class)->update($this->owner, $project->fresh(), ['status' => 'completed']);

        $this->assertSame(3, $project->history()->count());
        $this->assertDatabaseHas('project_history', ['project_id' => $project->id, 'event' => 'status_changed']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ProjectService::class)->update($this->owner, $project->fresh(), ['status' => 'active']);
    }

    public function test_project_lead_is_added_to_membership_roster(): void
    {
        $lead = OrganizationMembership::create([
            'organization_id' => $this->owner->organization_id,
            'user_id' => User::factory()->create()->id,
            'status' => 'active',
        ]);
        $project = app(ProjectService::class)->create($this->owner, ['name' => 'Led project', 'lead_membership_id' => $lead->id]);

        $this->assertTrue($project->members()->whereKey($lead->id)->exists());
        $this->assertDatabaseHas('project_history', ['project_id' => $project->id, 'event' => 'created']);
    }

    public function test_attendance_prevents_two_open_sessions_and_produces_summary(): void
    {
        $service = app(AttendanceService::class);
        $session = $service->clockIn($this->owner);
        $threw = false;
        try { $service->clockIn($this->owner); } catch (\Symfony\Component\HttpKernel\Exception\HttpException) { $threw = true; }
        $this->assertTrue($threw);
        $service->clockOut($this->owner);
        $this->assertNotNull($session->fresh()->clocked_out_at);
    }

    public function test_leave_reserves_balance_and_rejects_overlapping_request(): void
    {
        $type = LeaveType::create(['organization_id' => $this->owner->organization_id, 'name' => 'Annual']);
        LeaveBalance::create(['organization_id' => $this->owner->organization_id, 'membership_id' => $this->owner->id, 'leave_type_id' => $type->id, 'year' => '2026', 'allocated_days' => 10]);
        $service = app(LeaveService::class);
        $request = $service->request($this->owner, ['leave_type_id' => $type->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-03', 'days' => 2]);
        $this->assertSame('pending_manager', $request->status);
        $this->assertSame('2.00', (string) LeaveBalance::first()->reserved_days);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->request($this->owner, ['leave_type_id' => $type->id, 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-04', 'days' => 2]);
    }

    public function test_payroll_generation_is_repeatable_and_snapshotted(): void
    {
        Compensation::create(['organization_id' => $this->owner->organization_id, 'membership_id' => $this->owner->id, 'currency' => 'NGN', 'base_amount' => 250000, 'effective_from' => '2026-01-01']);
        $period = app(PayrollService::class)->generate($this->owner, ['period' => '2026-10', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
        $again = app(PayrollService::class)->generate($this->owner, ['period' => '2026-10', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
        $this->assertSame($period->id, $again->id);
        $this->assertCount(1, $again->lines);
        $this->assertSame('250000.00', (string) $again->lines->sole()->net_amount);
    }

    public function test_document_upload_stays_quarantined_until_scanner_marks_it_clean(): void
    {
        Storage::fake('private');
        $document = app(\App\Services\DocumentService::class)->upload($this->owner, UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf'));
        $this->assertSame('quarantined', $document->status);
        $this->assertSame('pending', $document->versions->sole()->scan_status);
        $this->assertTrue(Storage::disk('private')->exists($document->versions->sole()->storage_key));
    }

    public function test_report_pdf_is_generated_from_an_immutable_snapshot(): void
    {
        $template = ReportTemplate::create(['organization_id' => $this->owner->organization_id, 'name' => 'Weekly', 'code' => 'weekly']);
        $report = Report::create(['organization_id' => $this->owner->organization_id, 'template_id' => $template->id, 'membership_id' => $this->owner->id, 'period_start' => '2026-10-26', 'period_end' => '2026-11-01', 'status' => 'submitted']);
        $report->versions()->create(['organization_id' => $this->owner->organization_id, 'version' => 1, 'created_by_membership_id' => $this->owner->id, 'snapshot' => ['work_completed' => 'Launch plan'], 'submitted_at' => now()]);
        $pdf = app(\App\Services\ReportPdfService::class)->render($report);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Launch plan', $pdf);
    }
}
