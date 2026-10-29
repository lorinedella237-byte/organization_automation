<?php
namespace App\Services;
use App\Models\OrganizationMembership; use App\Models\Project; use App\Models\Task; use App\Models\AttendanceSession; use App\Models\LeaveRequest; use App\Models\PayrollPeriod;
class DashboardService
{
    public function __construct(private WorkspaceAccess $access) {}
    public function for(OrganizationMembership $actor): array
    {
        $projects=app(ProjectService::class)->visibleProjects($actor); $members=app(WorkspaceAccess::class)->visibleMembers($actor);
        return ['workspace_id'=>$actor->organization_id,'staff'=>['visible'=>$members->count(),'active'=>(clone $members)->where('status','active')->count()], 'projects'=>['visible'=>(clone $projects)->count(),'active'=>(clone $projects)->where('status','active')->count()], 'tasks'=>['open'=>Task::whereIn('project_id',(clone $projects)->select('id'))->whereNotIn('status',['done','cancelled'])->count(),'overdue'=>Task::whereIn('project_id',(clone $projects)->select('id'))->whereNotIn('status',['done','cancelled'])->whereNotNull('due_on')->whereDate('due_on','<',today())->count()], 'attendance'=>['open'=>AttendanceSession::where('organization_id',$actor->organization_id)->where('membership_id',$actor->id)->whereNull('clocked_out_at')->count()], 'leave'=>['pending'=>LeaveRequest::where('organization_id',$actor->organization_id)->where(function($q)use($actor){$q->where('membership_id',$actor->id)->orWhereIn('status',['pending_manager','pending_hr']);})->count()], 'payroll'=>['drafts'=>PayrollPeriod::where('organization_id',$actor->organization_id)->where('status','draft')->count()]];
    }
}
