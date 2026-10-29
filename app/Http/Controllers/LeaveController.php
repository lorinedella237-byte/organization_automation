<?php
namespace App\Http\Controllers;
use App\Models\LeaveRequest; use App\Models\OrganizationMembership; use App\Services\LeaveService; use Illuminate\Http\Request;
class LeaveController extends Controller
{
    public function __construct(private LeaveService $leave) {}
    private function actor(Request $r): OrganizationMembership { return $r->attributes->get('currentMembership'); }
    public function index(Request $r) { $a=$this->actor($r); return response()->json(LeaveRequest::where('organization_id',$a->organization_id)->where(function($q)use($a){$q->where('membership_id',$a->id)->orWhere('status','pending_manager');})->with(['leaveType','membership.user'])->latest()->paginate(50)); }
    public function store(Request $r) { return response()->json(['data'=>$this->leave->request($this->actor($r),$r->all())],201); }
    public function decide(Request $r,int $request) { $leave=LeaveRequest::where('organization_id',$this->actor($r)->organization_id)->findOrFail($request); $data=$r->validate(['decision'=>['required','in:approved,rejected'],'comment'=>['nullable','string','max:2000']]); return response()->json(['data'=>$this->leave->decide($this->actor($r),$leave,$data['decision'],$data['comment']??null)]); }
    public function cancel(Request $r,int $request) { $leave=LeaveRequest::where('organization_id',$this->actor($r)->organization_id)->findOrFail($request); return response()->json(['data'=>$this->leave->cancel($this->actor($r),$leave)]); }
}
