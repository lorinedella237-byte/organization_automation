<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use App\Services\WorkspaceProvisioner;
use Tests\TestCase;

/**
 * The Phase 2 screens. Every other test of this module sends
 * Accept: application/json, so none of them renders a template; these assert
 * the HTML branch and the arithmetic the templates do on top of it.
 *
 * Progress is the part worth testing rather than eyeballing: it has to exclude
 * cancelled work, and a project whose tasks were all cancelled has to read as
 * not started rather than as finished.
 */
class ProjectScreensRenderTest extends TestCase
{
    private Organization $organization;

    private OrganizationMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), [
            'name' => 'Projects', 'slug' => 'projects', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $this->owner = $this->organization->memberships()->sole();
    }

    private function project(array $attributes = []): Project
    {
        return Project::create($attributes + [
            'organization_id' => $this->organization->id,
            'owner_membership_id' => $this->owner->id,
            'name' => 'Harbour Rebuild',
            'status' => 'active',
        ]);
    }

    public function test_the_list_renders_html_and_still_answers_json(): void
    {
        $this->actingAs($this->owner->user);
        $this->project(['code' => 'HARB']);

        $this->get('/workspace/projects')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Harbour Rebuild')
            ->assertSee('HARB');

        $this->getJson('/workspace/projects')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_an_empty_list_and_an_empty_filter_result_say_different_things(): void
    {
        $this->actingAs($this->owner->user);

        $this->get('/workspace/projects')->assertOk()->assertSee('No projects yet');

        $this->project();

        $this->get('/workspace/projects?search=nothing-matches-this')
            ->assertOk()
            ->assertSee('No projects match those filters')
            ->assertDontSee('No projects yet');
    }

    public function test_a_project_can_be_created_from_a_browser_form(): void
    {
        $this->actingAs($this->owner->user);

        // A browser form posts without Accept: application/json and must be
        // answered with a redirect to the new project, not a JSON body.
        $this->post('/workspace/projects', [
            'name' => 'Harbour Rebuild',
            'code' => 'HARB',
            'status' => 'planned',
            'description' => 'Replacing the north quay.',
        ])->assertRedirect();

        $project = Project::sole();
        $this->assertSame('Harbour Rebuild', $project->name);
        $this->get('/workspace/projects/'.$project->id)->assertOk()->assertSee('Replacing the north quay.');
    }

    public function test_a_project_with_no_tasks_reads_as_not_started(): void
    {
        $this->actingAs($this->owner->user);
        $project = $this->project();

        $this->get('/workspace/projects/'.$project->id)
            ->assertOk()
            ->assertSee('Not started')
            ->assertSee('No tasks have been added to this project.')
            ->assertSee('No tasks yet');
    }

    public function test_progress_excludes_cancelled_tasks(): void
    {
        $this->actingAs($this->owner->user);
        $project = $this->project();

        foreach (['done', 'done', 'todo', 'cancelled'] as $status) {
            $project->tasks()->create([
                'organization_id' => $this->organization->id,
                'title' => 'Task '.$status,
                'status' => $status,
            ]);
        }

        // Two of the three tasks that still count are done. Counting the
        // cancelled one would give 50 per cent instead.
        $this->get('/workspace/projects/'.$project->id)
            ->assertOk()
            ->assertSee('67%')
            ->assertSee('2 of 3 tasks done')
            ->assertSee('1 cancelled task is not counted');
    }

    public function test_a_project_whose_every_task_was_cancelled_is_not_complete(): void
    {
        $this->actingAs($this->owner->user);
        $project = $this->project();

        $project->tasks()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Abandoned',
            'status' => 'cancelled',
        ]);

        $this->get('/workspace/projects/'.$project->id)
            ->assertOk()
            ->assertSee('Not started')
            ->assertSee('Every task on this project is cancelled')
            ->assertDontSee('100%');
    }

    public function test_overdue_and_blocked_work_stays_visible(): void
    {
        $this->actingAs($this->owner->user);
        $project = $this->project();

        $project->tasks()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Late survey', 'status' => 'todo', 'due_on' => now()->subDays(3)->format('Y-m-d'),
        ]);
        $project->tasks()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Waiting on permits', 'status' => 'blocked',
        ]);
        // A past due date on finished work is not a problem and must not count.
        $project->tasks()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Finished late', 'status' => 'done', 'due_on' => now()->subDays(9)->format('Y-m-d'),
        ]);

        $this->get('/workspace/projects/'.$project->id)
            ->assertOk()
            ->assertSee('1 overdue task')
            ->assertSee('1 blocked task')
            ->assertSee('Overdue');
    }

    public function test_tasks_can_be_added_and_restatused_from_browser_forms(): void
    {
        $this->actingAs($this->owner->user);
        $project = $this->project();

        $this->post('/workspace/projects/'.$project->id.'/tasks', [
            'title' => 'Survey the quay',
            'status' => 'todo',
            'priority' => 'high',
            'assignee_membership_id' => $this->owner->id,
        ])->assertRedirect();

        $task = $project->tasks()->sole();
        $this->assertSame('Survey the quay', $task->title);

        // The inline control on the detail page posts exactly this shape.
        $this->patch('/workspace/tasks/'.$task->id, ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);

        $this->get('/workspace/projects/'.$project->id)
            ->assertOk()
            ->assertSee('Survey the quay')
            ->assertSee('High');
    }

    public function test_a_project_outside_the_viewers_scope_is_not_found(): void
    {
        $other = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), [
            'name' => 'Elsewhere', 'slug' => 'elsewhere', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);

        $foreign = Project::create([
            'organization_id' => $other->id,
            'owner_membership_id' => $other->memberships()->sole()->id,
            'name' => 'Not yours',
            'status' => 'active',
        ]);

        // Not found rather than forbidden, so the page does not confirm that
        // the record exists in some other workspace.
        $this->actingAs($this->owner->user)
            ->get('/workspace/projects/'.$foreign->id)
            ->assertNotFound();
    }
}
