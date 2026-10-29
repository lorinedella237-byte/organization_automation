<?php
namespace App\Services;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceSession;
use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function __construct(private WorkspaceAccess $access, private AuditRecorder $audit) {}
    public function clockIn(OrganizationMembership $actor): AttendanceSession
    {
        $this->access->authorize($actor, 'attendance.clock_in', $actor);
        return DB::transaction(function () use ($actor) {
            $open = AttendanceSession::where('organization_id',$actor->organization_id)->where('membership_id',$actor->id)->whereNull('clocked_out_at')->lockForUpdate()->first();
            abort_if($open, 422, 'An attendance session is already open.');
            $session = AttendanceSession::create(['organization_id'=>$actor->organization_id,'membership_id'=>$actor->id,'clocked_in_at'=>now(),'local_date'=>now()->timezone($actor->organization->timezone ?? 'UTC')->toDateString(),'status'=>'open']);
            $this->audit->record($actor->organization_id,$actor,'attendance.clocked_in','attendance_session',$session->id); return $session;
        });
    }
    public function clockOut(OrganizationMembership $actor): AttendanceSession
    {
        $this->access->authorize($actor, 'attendance.clock_out', $actor);
        return DB::transaction(function () use ($actor) { $session = AttendanceSession::where('organization_id',$actor->organization_id)->where('membership_id',$actor->id)->whereNull('clocked_out_at')->lockForUpdate()->firstOrFail(); $session->clocked_out_at=now(); $session->duration_minutes=max(0,$session->clocked_in_at->diffInMinutes($session->clocked_out_at)); $session->status='complete'; $session->save(); $this->audit->record($actor->organization_id,$actor,'attendance.clocked_out','attendance_session',$session->id); return $session; });
    }
    public function requestCorrection(OrganizationMembership $actor, array $input): AttendanceCorrectionRequest
    {
        $data=Validator::make($input,['attendance_session_id'=>['nullable','integer'],'requested_clocked_in_at'=>['nullable','date'],'requested_clocked_out_at'=>['nullable','date','after_or_equal:requested_clocked_in_at'],'reason'=>['required','string','max:1000']])->validate();
        $session=isset($data['attendance_session_id'])?AttendanceSession::where('organization_id',$actor->organization_id)->where('membership_id',$actor->id)->findOrFail($data['attendance_session_id']):null;
        $request=AttendanceCorrectionRequest::create($data+['organization_id'=>$actor->organization_id,'membership_id'=>$actor->id,'attendance_session_id'=>$session?->id]); $this->audit->record($actor->organization_id,$actor,'attendance.correction_requested','attendance_correction_request',$request->id); return $request;
    }

    public function summary(OrganizationMembership $actor, string $from, string $to): array
    {
        $data = Validator::make(['from' => $from, 'to' => $to], ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']])->validate();
        $sessions = AttendanceSession::where('organization_id', $actor->organization_id)->where('membership_id', $actor->id)->whereBetween('local_date', [$data['from'], $data['to']])->orderBy('local_date')->get();
        $leaveDates = \App\Models\LeaveRequest::where('organization_id', $actor->organization_id)->where('membership_id', $actor->id)->where('status', 'approved')->where('starts_on', '<=', $data['to'])->where('ends_on', '>=', $data['from'])->get(['starts_on','ends_on']);
        $calendar=\App\Models\WorkCalendar::where('organization_id',$actor->organization_id)->where('is_default',true)->first(); $schedule=$calendar?->weekly_schedule??[]; $grace=(int)($calendar?->grace_minutes??0);
        $byDate = $sessions->groupBy(fn ($session) => $session->local_date->toDateString());
        $rows = [];
        for ($date = Carbon::parse($data['from']); $date->lte(Carbon::parse($data['to'])); $date->addDay()) {
            $key = $date->toDateString(); $day = $byDate->get($key, collect()); $onLeave=$leaveDates->contains(fn($leave)=>$leave->starts_on->toDateString()<=$key&&$leave->ends_on->toDateString()>=$key);
            $status=$onLeave?'leave':($day->isEmpty()?'missing':($day->contains('status','incomplete')?'incomplete':($day->contains('status','open')?'open':'present'))); $scheduled=$schedule[(string)$date->dayOfWeekIso]??$schedule[$date->dayOfWeekIso]??null; if(!$onLeave&&$scheduled&&$day->isNotEmpty()){ $start=$scheduled['start']??$scheduled['work_start']??null; $end=$scheduled['end']??$scheduled['work_end']??null; $clockIn=$day->sortBy('clocked_in_at')->first()?->clocked_in_at; $clockOut=$day->sortByDesc('clocked_out_at')->first()?->clocked_out_at; if($start&&$clockIn&&$clockIn->timezone($calendar?->timezone??'UTC')->format('H:i')>date('H:i',strtotime($start.' +'.$grace.' minutes'))) $status='late'; if($end&&$clockOut&&$clockOut->timezone($calendar?->timezone??'UTC')->format('H:i')<=$end) $status='early'; } $rows[] = ['date' => $key, 'status' => $status, 'minutes' => (int) $day->sum('duration_minutes')];
        }
        return $rows;
    }

    public function decideCorrection(OrganizationMembership $actor, AttendanceCorrectionRequest $request, string $decision): AttendanceCorrectionRequest
    {
        abort_unless($request->organization_id === $actor->organization_id, 404); $this->access->authorize($actor, 'attendance.correct', $request->membership); abort_unless(in_array($decision, ['approved', 'rejected'], true), 422); abort_unless($request->status === 'pending', 422, 'This correction request is already decided.');
        return DB::transaction(function () use ($actor, $request, $decision) { $request=AttendanceCorrectionRequest::whereKey($request->id)->lockForUpdate()->firstOrFail(); if($decision==='approved' && $request->session){$session=$request->session; $before=['clocked_in_at'=>$session->clocked_in_at,'clocked_out_at'=>$session->clocked_out_at]; if($request->requested_clocked_in_at)$session->clocked_in_at=$request->requested_clocked_in_at; if($request->requested_clocked_out_at){$session->clocked_out_at=$request->requested_clocked_out_at;$session->duration_minutes=$session->clocked_in_at->diffInMinutes($session->clocked_out_at);$session->status='complete';}$session->save();DB::table('attendance_corrections')->insert(['organization_id'=>$actor->organization_id,'request_id'=>$request->id,'session_id'=>$session->id,'before_values'=>json_encode($before),'after_values'=>json_encode(['clocked_in_at'=>$session->clocked_in_at,'clocked_out_at'=>$session->clocked_out_at]),'actor_membership_id'=>$actor->id,'created_at'=>now()]);}$request->update(['status'=>$decision,'decided_by_membership_id'=>$actor->id]);$this->audit->record($actor->organization_id,$actor,'attendance.correction_'.$decision,'attendance_correction_request',$request->id);return $request;});
    }
}
