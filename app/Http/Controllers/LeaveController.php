<?php
namespace App\Http\Controllers;
use App\Models\LeaveRequest; use App\Models\OrganizationMembership; use App\Services\LeaveService; use Illuminate\Http\Request;
class LeaveController extends Controller
{
    public function __construct(private LeaveService $leave) {}
    private function actor(Request $r): OrganizationMembership { return $r->attributes->get('currentMembership'); }
    public function index(Request $r)
    {
        $a = $this->actor($r);
        // Claude, 2026-10-29: an unscoped "or status = pending_manager" showed
        // every pending request in the organization to every member. Another
        // person's request is now visible only when the reader's leave.view
        // scope covers them, which is what makes this an approver queue
        // rather than a notice board.
        $visible = app(\App\Services\WorkspaceAccess::class)->visibleMembers($a, 'leave.view')->select('id');
        $page = LeaveRequest::where('organization_id', $a->organization_id)
            ->where(function ($q) use ($a, $visible) {
                $q->where('membership_id', $a->id)
                    ->orWhere(function ($q) use ($visible) { $q->whereIn('membership_id', $visible)->where('status', 'pending_manager'); });
            })->with(['leaveType', 'membership.user'])->latest()->paginate(50);
        if ($r->expectsJson()) {
            return response()->json($page);
        }

        // A request form with no leave type to choose would fail validation on
        // every submission, so the page says so instead of rendering one.
        return view('workspace.leave.index', [
            'page' => $page,
            'actor' => $a,
            'types' => \App\Models\LeaveType::where('organization_id', $a->organization_id)->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'balances' => \App\Models\LeaveBalance::where('organization_id', $a->organization_id)->where('membership_id', $a->id)->where('year', now()->format('Y'))->with('leaveType')->get(),
        ]);
    }
    public function store(Request $r) { $leave=$this->leave->request($this->actor($r),$r->all()); return $r->expectsJson()?response()->json(['data'=>$leave],201):redirect()->route('leave.index')->with('success','Leave requested.'); }
    public function decide(Request $r,int $request) { $leave=LeaveRequest::where('organization_id',$this->actor($r)->organization_id)->findOrFail($request); $data=$r->validate(['decision'=>['required','in:approved,rejected'],'comment'=>['nullable','string','max:2000']]); $decided=$this->leave->decide($this->actor($r),$leave,$data['decision'],$data['comment']??null); return $r->expectsJson()?response()->json(['data'=>$decided]):redirect()->route('leave.index')->with('success','Leave request '.$data['decision'].'.'); }
    public function cancel(Request $r,int $request) { $leave=LeaveRequest::where('organization_id',$this->actor($r)->organization_id)->findOrFail($request); $cancelled=$this->leave->cancel($this->actor($r),$leave); return $r->expectsJson()?response()->json(['data'=>$cancelled]):redirect()->route('leave.index')->with('success','Leave request cancelled.'); }
}
