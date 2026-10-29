<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectHistory;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\ReportTemplate;
use App\Models\ReportVersion;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    private const PROJECT_STATUSES = ['planned', 'active', 'on_hold', 'completed', 'archived'];

    private const STATUS_TRANSITIONS = [
        'planned' => ['active', 'on_hold', 'archived'],
        'active' => ['on_hold', 'completed', 'archived'],
        'on_hold' => ['active', 'completed', 'archived'],
        'completed' => ['archived'],
        'archived' => [],
    ];

    public function __construct(private WorkspaceAccess $access, private AuditRecorder $audit, private WorkspaceNotifications $notifications) {}

    public function visibleProjects(OrganizationMembership $actor)
    {
        $scopes = $this->access->scopes($actor, 'projects.view');
        $query = Project::where('organization_id', $actor->organization_id);
        if (in_array('organization', $scopes, true)) return $query;
        $query->where(function ($q) use ($actor, $scopes) {
            $q->where('owner_membership_id', $actor->id)->orWhere('lead_membership_id', $actor->id);
            if (in_array('assigned_project', $scopes, true)) $q->orWhereHas('members', fn ($m) => $m->where('organization_memberships.id', $actor->id));
            if (in_array('department', $scopes, true) && $actor->department_id) $q->orWhere('department_id', $actor->department_id);
        });
        return $query;
    }

    public function create(OrganizationMembership $actor, array $input): Project
    {
        $this->access->authorize($actor, 'projects.manage');
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:180'], 'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(self::PROJECT_STATUSES)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', $actor->organization_id)],
            'lead_membership_id' => ['nullable', 'integer', Rule::exists('organization_memberships', 'id')->where(fn ($q) => $q->where('organization_id', $actor->organization_id)->where('status', 'active'))],
            'starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();
        if (! empty($data['starts_on']) && ! empty($data['ends_on']) && $data['ends_on'] < $data['starts_on']) throw ValidationException::withMessages(['ends_on' => 'End date must not precede the start date.']);
        return DB::transaction(function () use ($actor, $data) {
            $project = Project::create($data + ['organization_id' => $actor->organization_id, 'owner_membership_id' => $actor->id, 'status' => 'planned']);
            $project->members()->syncWithoutDetaching([$actor->id => ['organization_id' => $actor->organization_id]]);
            if ($project->lead_membership_id) $project->members()->syncWithoutDetaching([$project->lead_membership_id => ['organization_id' => $actor->organization_id, 'role' => 'lead']]);
            $this->recordProjectHistory($actor, $project, 'created', null, $project->only(['name', 'code', 'status', 'owner_membership_id', 'lead_membership_id', 'department_id', 'starts_on', 'ends_on']));
            $this->audit->record($actor->organization_id, $actor, 'project.created', 'project', $project->id);
            return $project;
        });
    }

    public function update(OrganizationMembership $actor, Project $project, array $input): Project
    {
        $this->authorizeProject($actor, $project, 'projects.manage');
        $data = Validator::make($input, ['name' => ['sometimes', 'string', 'max:180'], 'description' => ['sometimes', 'nullable', 'string'], 'status' => ['sometimes', Rule::in(self::PROJECT_STATUSES)], 'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', $actor->organization_id)], 'lead_membership_id' => ['sometimes', 'nullable', 'integer', Rule::exists('organization_memberships', 'id')->where(fn ($q) => $q->where('organization_id', $actor->organization_id)->where('status', 'active'))], 'starts_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d']])->validate();
        $before = $project->getOriginal();
        $project->fill($data);
        if ($project->starts_on && $project->ends_on && $project->ends_on->lt($project->starts_on)) throw ValidationException::withMessages(['ends_on' => 'End date must not precede the start date.']);
        if ($project->isDirty('status') && ! in_array($project->status, self::STATUS_TRANSITIONS[$project->getOriginal('status')] ?? [], true)) throw ValidationException::withMessages(['status' => 'That project status transition is not allowed.']);
        $changes = array_keys($project->getDirty());
        if ($project->isDirty('lead_membership_id') && $project->lead_membership_id) $project->members()->syncWithoutDetaching([$project->lead_membership_id => ['organization_id' => $actor->organization_id, 'role' => 'lead']]);
        $project->save();
        if ($changes) $this->recordProjectHistory($actor, $project, $project->wasChanged('status') ? 'status_changed' : 'updated', array_intersect_key($before, array_flip($changes)), $project->only($changes));
        $this->audit->record($actor->organization_id, $actor, 'project.updated', 'project', $project->id, ['changed_fields' => $changes]);
        return $project;
    }

    public function createTask(OrganizationMembership $actor, Project $project, array $input): Task
    {
        $this->authorizeProject($actor, $project, 'tasks.assign');
        $data = Validator::make($input, ['title' => ['required', 'string', 'max:220'], 'description' => ['nullable', 'string'], 'milestone_id' => ['nullable', 'integer', Rule::exists('milestones', 'id')->where('project_id', $project->id)], 'assignee_membership_id' => ['nullable', 'integer', Rule::exists('organization_memberships', 'id')->where('organization_id', $actor->organization_id)], 'status' => ['nullable', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])], 'due_on' => ['nullable', 'date_format:Y-m-d']])->validate();
        return DB::transaction(function () use ($actor, $project, $data) {
            $task = $project->tasks()->create($data + ['organization_id' => $actor->organization_id]);
            DB::table('task_history')->insert(['organization_id' => $actor->organization_id, 'task_id' => $task->id, 'actor_membership_id' => $actor->id, 'event' => 'created', 'before_values' => null, 'after_values' => json_encode($task->only(['title', 'status', 'priority', 'assignee_membership_id'])), 'created_at' => now()]);
            $this->audit->record($actor->organization_id, $actor, 'task.created', 'task', $task->id);
            return $task;
        });
    }

    public function updateTask(OrganizationMembership $actor, Task $task, array $input): Task
    {
        $this->authorizeProject($actor, $task->project, 'tasks.assign');
        $data = Validator::make($input, ['title' => ['sometimes', 'string', 'max:220'], 'description' => ['sometimes', 'nullable', 'string'], 'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])], 'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])], 'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'assignee_membership_id' => ['sometimes', 'nullable', 'integer', Rule::exists('organization_memberships', 'id')->where('organization_id', $actor->organization_id)]])->validate();
        return DB::transaction(function () use ($actor, $task, $data) {
            $before = $task->only(array_keys($data));
            $task->fill($data); $changes = array_keys($task->getDirty()); $task->save();
            if ($changes) DB::table('task_history')->insert(['organization_id' => $actor->organization_id, 'task_id' => $task->id, 'actor_membership_id' => $actor->id, 'event' => 'updated', 'before_values' => json_encode($before), 'after_values' => json_encode($task->only(array_keys($data))), 'created_at' => now()]);
            $this->audit->record($actor->organization_id, $actor, 'task.updated', 'task', $task->id, ['changed_fields' => $changes]);
            return $task;
        });
    }

    public function addTaskComment(OrganizationMembership $actor, Task $task, string $body): object
    {
        abort_unless($task->organization_id === $actor->organization_id, 404);
        $this->access->authorize($actor, 'tasks.view');
        $body = trim($body);
        abort_if($body === '' || mb_strlen($body) > 5000, 422, 'Comment must contain between 1 and 5000 characters.');
        $comment = $task->comments()->create(['organization_id' => $actor->organization_id, 'membership_id' => $actor->id, 'body' => $body]);
        $this->audit->record($actor->organization_id, $actor, 'task.comment_added', 'task', $task->id);
        return $comment;
    }

    public function saveChecklistItem(OrganizationMembership $actor, Task $task, array $input, ?int $itemId = null): object
    {
        abort_unless($task->organization_id === $actor->organization_id, 404);
        $this->access->authorize($actor, 'tasks.assign');
        $data = Validator::make($input, ['label' => ['required', 'string', 'max:255'], 'completed' => ['sometimes', 'boolean']])->validate();
        $item = $itemId ? $task->checklist()->findOrFail($itemId) : $task->checklist()->make(['organization_id' => $actor->organization_id]);
        $item->fill($data);
        if ($item->completed && ! $item->completed_at) { $item->completed_at = now(); $item->completed_by_membership_id = $actor->id; }
        if (! $item->completed) { $item->completed_at = null; $item->completed_by_membership_id = null; }
        $item->save();
        return $item;
    }

    public function assignMember(OrganizationMembership $actor, Project $project, int $membershipId, ?string $role = null): void
    {
        $this->authorizeProject($actor, $project, 'projects.manage');
        $member = OrganizationMembership::where('organization_id', $actor->organization_id)->whereKey($membershipId)->where('status', 'active')->firstOrFail();
        $project->members()->syncWithoutDetaching([$member->id => ['organization_id' => $actor->organization_id, 'role' => $role]]);
        $this->recordProjectHistory($actor, $project, 'member_assigned', null, ['membership_id' => $member->id, 'role' => $role]);
        $this->notifications->record($member, 'project.assigned:'.$project->id, 'You were assigned to project '.$project->name.'.');
        $this->audit->record($actor->organization_id, $actor, 'project.member_assigned', 'project', $project->id);
    }

    public function history(OrganizationMembership $actor, Project $project)
    {
        $this->authorizeProject($actor, $project, 'projects.view');
        return $project->history()->with('actor.user')->get();
    }

    private function authorizeProject(OrganizationMembership $actor, Project $project, string $ability): void
    {
        abort_unless($project->organization_id === $actor->organization_id, 404);
        $scopes = $this->access->scopes($actor, $ability);
        if (in_array('organization', $scopes, true)) return;
        abort_unless(in_array('assigned_project', $scopes, true), 403);
        $isMember = $project->owner_membership_id === $actor->id
            || $project->lead_membership_id === $actor->id
            || $project->members()->whereKey($actor->id)->exists();
        abort_unless($isMember, 403);
    }

    private function recordProjectHistory(OrganizationMembership $actor, Project $project, string $event, ?array $before, ?array $after): void
    {
        ProjectHistory::create(['organization_id' => $actor->organization_id, 'project_id' => $project->id, 'actor_membership_id' => $actor->id, 'event' => $event, 'before_values' => $before, 'after_values' => $after, 'created_at' => now()]);
    }

    public function saveWorkLog(OrganizationMembership $actor, array $input, ?\App\Models\WorkLog $log = null): \App\Models\WorkLog
    {
        $data = Validator::make($input, ['work_date' => ['required', 'date_format:Y-m-d'], 'hours' => ['required', 'numeric', 'gt:0', 'lte:24'], 'comment' => ['nullable', 'string', 'max:2000'], 'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('organization_id', $actor->organization_id)], 'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('organization_id', $actor->organization_id)]])->validate();
        if ($log) abort_unless($log->organization_id === $actor->organization_id && $log->membership_id === $actor->id && $log->status === 'pending', 404);
        else $this->access->authorize($actor, 'activities.create', $actor);
        $log ??= new \App\Models\WorkLog(['organization_id' => $actor->organization_id, 'membership_id' => $actor->id, 'status' => 'pending']);
        $log->fill($data); $log->save();
        return $log;
    }

    public function approveWorkLog(OrganizationMembership $actor, \App\Models\WorkLog $log): \App\Models\WorkLog
    {
        abort_unless($log->organization_id === $actor->organization_id, 404);
        $this->access->authorize($actor, 'reports.review', $log->membership);
        abort_if($log->membership_id === $actor->id, 403, 'A member cannot approve their own work log.');
        abort_unless($log->status === 'pending', 422, 'Only pending work logs can be approved.');
        $log->update(['status' => 'approved', 'approved_by_membership_id' => $actor->id, 'approved_at' => now()]);
        $this->audit->record($actor->organization_id, $actor, 'work_log.approved', 'work_log', $log->id);
        return $log;
    }

    public function saveActivity(OrganizationMembership $actor, array $input, ?DailyActivity $activity = null): DailyActivity
    {
        $data = Validator::make($input, ['activity_date' => ['required', 'date_format:Y-m-d'], 'title' => ['required', 'string', 'max:180'], 'narrative' => ['nullable', 'string'], 'blockers' => ['nullable', 'string'], 'progress' => ['nullable', 'integer', 'min:0', 'max:100'], 'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'], 'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('organization_id', $actor->organization_id)], 'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('organization_id', $actor->organization_id)]])->validate();
        if ($activity) abort_unless($activity->organization_id === $actor->organization_id && $activity->membership_id === $actor->id, 404);
        else $this->access->authorize($actor, 'activities.create', $actor);
        $activity ??= new DailyActivity(['organization_id' => $actor->organization_id, 'membership_id' => $actor->id]);
        $activity->fill($data); $activity->save();
        $this->audit->record($actor->organization_id, $actor, 'activity.saved', 'daily_activity', $activity->id);
        return $activity;
    }

    public function submitReport(OrganizationMembership $actor, Report $report, array $snapshot): ReportVersion
    {
        abort_unless($report->organization_id === $actor->organization_id && $report->membership_id === $actor->id, 404);
        abort_if($report->status === 'approved', 422, 'An approved report cannot be changed.');
        $version = (int) $report->versions()->max('version') + 1;
        return DB::transaction(function () use ($actor, $report, $snapshot, $version) {
            $versionModel = $report->versions()->create(['organization_id' => $actor->organization_id, 'version' => $version, 'created_by_membership_id' => $actor->id, 'snapshot' => $snapshot, 'submitted_at' => now()]);
            $report->update(['status' => 'submitted', 'submitted_at' => now()]);
            $this->audit->record($actor->organization_id, $actor, 'report.submitted', 'report', $report->id);
            return $versionModel;
        });
    }

    public function reviewReport(OrganizationMembership $actor, Report $report, string $decision, ?string $comment = null): ReportReview
    {
        abort_unless($report->organization_id === $actor->organization_id, 404);
        $this->access->authorize($actor, 'reports.review', $report->membership);
        abort_if($report->membership_id === $actor->id, 403, 'A report author cannot review their own report.');
        abort_unless(in_array($decision, ['returned', 'approved'], true), 422);
        return DB::transaction(function () use ($actor, $report, $decision, $comment) {
            $review = $report->reviews()->create(['organization_id' => $actor->organization_id, 'reviewer_membership_id' => $actor->id, 'decision' => $decision, 'comment' => $comment, 'created_at' => now()]);
            $report->update(['status' => $decision]);
            $this->audit->record($actor->organization_id, $actor, 'report.reviewed', 'report', $report->id, ['after_status' => $decision]);
            $this->notifications->record($report->membership, 'report.reviewed:'.$report->id.':'.$decision, 'Your report was '.$decision.'.');
            return $review;
        });
    }
}
