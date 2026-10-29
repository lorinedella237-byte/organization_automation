<?php
namespace App\Http\Controllers;
use App\Models\OrganizationMembership; use App\Models\PayrollPeriod; use App\Models\PayrollLine; use App\Services\PayrollService; use Illuminate\Http\Request;
class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}
    private function actor(Request $r): OrganizationMembership { return $r->attributes->get('currentMembership'); }
    public function index(Request $r) { $a=$this->actor($r); return response()->json(PayrollPeriod::where('organization_id',$a->organization_id)->withCount('lines')->latest()->paginate(24)); }
    public function generate(Request $r) { return response()->json(['data'=>$this->payroll->generate($this->actor($r),$r->all())],201); }
    public function approve(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); return response()->json(['data'=>$this->payroll->approve($this->actor($r),$p)]); }
    public function submit(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); return response()->json(['data'=>$this->payroll->submit($this->actor($r),$p)]); }
    public function payment(Request $r,int $line) { $l=PayrollLine::where('organization_id',$this->actor($r)->organization_id)->findOrFail($line); return response()->json(['data'=>$this->payroll->recordPayment($this->actor($r),$l,$r->all())],201); }
    public function return(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); $data=$r->validate(['comment'=>['nullable','string','max:2000']]); return response()->json(['data'=>$this->payroll->returnForCorrection($this->actor($r),$p,$data['comment']??null)]); }
    public function lock(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); return response()->json(['data'=>$this->payroll->lock($this->actor($r),$p)]); }
    public function payslip(Request $r,int $line) { $a=$this->actor($r); $l=PayrollLine::where('organization_id',$a->organization_id)->with(['membership.user','period'])->findOrFail($line); abort_unless($l->membership_id===$a->id||app(\App\Services\WorkspaceAccess::class)->allows($a,'payroll.manage'),403); return response(app(\App\Services\PayslipPdfService::class)->render($l),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="payslip-'.$l->id.'.pdf"']); }
    public function export(Request $r): \Symfony\Component\HttpFoundation\StreamedResponse { $a=$this->actor($r); app(\App\Services\WorkspaceAccess::class)->authorize($a,'payroll.manage');$rows=PayrollLine::where('organization_id',$a->organization_id)->with(['membership.user','period'])->latest()->cursor();return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');fputcsv($out,['period','employee','currency','gross','adjustments','net']);foreach($rows as $row)fputcsv($out,[$row->period->period,$row->membership->user->name,$row->currency,$row->gross_amount,$row->adjustment_amount,$row->net_amount]);fclose($out);},'payroll.csv',['Content-Type'=>'text/csv']); }
}
