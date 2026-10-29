<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkLog;
use App\Services\ProjectService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    private function actor(Request $request): OrganizationMembership { return $request->attributes->get('currentMembership'); }

    public function index(Request $request)
    {
        $query = $this->projects->visibleProjects($this->actor($request))->withCount(['tasks', 'members']);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:planned,active,on_hold,completed,archived']]);
        if (filled($data['search'] ?? null)) { $term = '%'.addcslashes($data['search'], '%_\\').'%'; $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term)); }
        if (filled($data['status'] ?? null)) $query->where('status', $data['status']);
        $page = $query->latest()->paginate(25)->withQueryString();
        return $request->expectsJson() ? response()->json($page) : view('workspace.projects.index', compact('page'));
    }

    public function store(Request $request)
    {
        $project = $this->projects->create($this->actor($request), $request->all());
        return $request->expectsJson() ? response()->json(['data' => $project], 201) : redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, int $project)
    {
        $project = $this->projects->visibleProjects($this->actor($request))->with(['tasks.assignee.user', 'milestones'])->findOrFail($project);
        return $request->expectsJson() ? response()->json(['data' => $project]) : view('workspace.projects.show', compact('project'));
    }

    public function update(Request $request, int $project)
    {
        $projectModel = Project::where('organization_id', $this->actor($request)->organization_id)->findOrFail($project);
        $projectModel = $this->projects->update($this->actor($request), $projectModel, $request->all());
        return $request->expectsJson() ? response()->json(['data' => $projectModel]) : redirect()->route('projects.show', $projectModel)->with('success', 'Project updated.');
    }

    public function task(Request $request, int $project)
    {
        $projectModel = Project::where('organization_id', $this->actor($request)->organization_id)->findOrFail($project);
        $task = $this->projects->createTask($this->actor($request), $projectModel, $request->all());
        return $request->expectsJson() ? response()->json(['data' => $task], 201) : redirect()->route('projects.show', $projectModel)->with('success', 'Task created.');
    }

    public function updateTask(Request $request, int $task)
    {
        $taskModel = Task::where('organization_id', $this->actor($request)->organization_id)->findOrFail($task);
        $taskModel = $this->projects->updateTask($this->actor($request), $taskModel, $request->all());
        return $request->expectsJson() ? response()->json(['data' => $taskModel]) : redirect()->back()->with('success', 'Task updated.');
    }

    public function comment(Request $request, int $task)
    {
        $taskModel = Task::where('organization_id', $this->actor($request)->organization_id)->findOrFail($task);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        return response()->json(['data' => $this->projects->addTaskComment($this->actor($request), $taskModel, $data['body'])], 201);
    }

    public function checklist(Request $request, int $task, ?int $item = null)
    {
        $taskModel = Task::where('organization_id', $this->actor($request)->organization_id)->findOrFail($task);
        return response()->json(['data' => $this->projects->saveChecklistItem($this->actor($request), $taskModel, $request->all(), $item)], 201);
    }

    public function member(Request $request, int $project)
    {
        $projectModel = Project::where('organization_id', $this->actor($request)->organization_id)->findOrFail($project);
        $data = $request->validate(['membership_id' => ['required', 'integer'], 'role' => ['nullable', 'string', 'max:50']]);
        $this->projects->assignMember($this->actor($request), $projectModel, $data['membership_id'], $data['role'] ?? null);
        return response()->json(['status' => 'assigned']);
    }

    public function activities(Request $request)
    {
        $actor = $this->actor($request);
        return response()->json(\App\Models\DailyActivity::where('organization_id', $actor->organization_id)->where('membership_id', $actor->id)->latest('activity_date')->paginate(50));
    }

    public function saveActivity(Request $request, ?int $activity = null)
    {
        $actor = $this->actor($request); $model = $activity ? \App\Models\DailyActivity::where('organization_id', $actor->organization_id)->findOrFail($activity) : null;
        return response()->json(['data' => $this->projects->saveActivity($actor, $request->all(), $model)], $model ? 200 : 201);
    }

    public function workLogs(Request $request)
    {
        $actor = $this->actor($request);
        return response()->json(WorkLog::where('organization_id', $actor->organization_id)->where('membership_id', $actor->id)->latest('work_date')->paginate(50));
    }

    public function saveWorkLog(Request $request, ?int $log = null)
    {
        $actor = $this->actor($request); $model = $log ? WorkLog::where('organization_id', $actor->organization_id)->findOrFail($log) : null;
        return response()->json(['data' => $this->projects->saveWorkLog($actor, $request->all(), $model)], $model ? 200 : 201);
    }

    public function approveWorkLog(Request $request, int $log)
    {
        $model = WorkLog::where('organization_id', $this->actor($request)->organization_id)->findOrFail($log);
        return response()->json(['data' => $this->projects->approveWorkLog($this->actor($request), $model)]);
    }

    public function myTasks(Request $request)
    {
        $actor = $this->actor($request);
        $query = Task::where('organization_id', $actor->organization_id)->where('assignee_membership_id', $actor->id)->whereNotIn('status', ['done', 'cancelled'])->with('project')->latest('due_on');
        return response()->json($query->paginate(50));
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $projects = $this->projects->visibleProjects($this->actor($request))->withCount(['tasks'])->cursor();
        return response()->streamDownload(function () use ($projects) { $out=fopen('php://output','w'); fputcsv($out,['id','name','code','status','task_count']); foreach($projects as $project) fputcsv($out,[$project->id,$project->name,$project->code,$project->status,$project->tasks_count]); fclose($out); }, 'projects.csv', ['Content-Type'=>'text/csv']);
    }
}
