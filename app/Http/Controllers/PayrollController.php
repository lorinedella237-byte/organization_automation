<?php
namespace App\Http\Controllers;
use App\Models\OrganizationMembership; use App\Models\PayrollPeriod; use App\Models\PayrollLine; use App\Services\PayrollService; use Illuminate\Http\Request;
class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}
    private function actor(Request $r): OrganizationMembership { return $r->attributes->get('currentMembership'); }
    public function index(Request $r)
    {
        $a = $this->actor($r);
        $page = PayrollPeriod::where('organization_id', $a->organization_id)->withCount('lines')->latest()->paginate(24);
        if ($r->expectsJson()) {
            return response()->json($page);
        }

        // Someone without payroll.manage reaches this screen for their own
        // payslips only, so the lines are filtered to theirs rather than the
        // page being refused.
        $manages = app(\App\Services\WorkspaceAccess::class)->allows($a, 'payroll.manage');
        $lines = PayrollLine::where('organization_id', $a->organization_id)
            ->when(! $manages, fn ($q) => $q->where('membership_id', $a->id))
            ->with(['membership.user', 'period', 'payments'])->latest()->limit(100)->get();

        return view('workspace.payroll.index', ['page' => $page, 'lines' => $lines, 'manages' => $manages]);
    }
    public function generate(Request $r) { $period=$this->payroll->generate($this->actor($r),$r->all()); return $r->expectsJson()?response()->json(['data'=>$period],201):redirect()->route('payroll.index')->with('success','Payroll period generated.'); }
    public function approve(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); $done=$this->payroll->approve($this->actor($r),$p); return $r->expectsJson()?response()->json(['data'=>$done]):redirect()->route('payroll.index')->with('success','Payroll period approved.'); }
    public function submit(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); $done=$this->payroll->submit($this->actor($r),$p); return $r->expectsJson()?response()->json(['data'=>$done]):redirect()->route('payroll.index')->with('success','Payroll period submitted for approval.'); }
    public function payment(Request $r,int $line) { $l=PayrollLine::where('organization_id',$this->actor($r)->organization_id)->findOrFail($line); $payment=$this->payroll->recordPayment($this->actor($r),$l,$r->all()); return $r->expectsJson()?response()->json(['data'=>$payment],201):redirect()->route('payroll.index')->with('success','Payment recorded.'); }
    public function return(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); $data=$r->validate(['comment'=>['nullable','string','max:2000']]); $done=$this->payroll->returnForCorrection($this->actor($r),$p,$data['comment']??null); return $r->expectsJson()?response()->json(['data'=>$done]):redirect()->route('payroll.index')->with('success','Payroll period returned for correction.'); }
    public function lock(Request $r,int $period) { $p=PayrollPeriod::where('organization_id',$this->actor($r)->organization_id)->findOrFail($period); $done=$this->payroll->lock($this->actor($r),$p); return $r->expectsJson()?response()->json(['data'=>$done]):redirect()->route('payroll.index')->with('success','Payroll period locked.'); }
    public function payslip(Request $r,int $line) { $a=$this->actor($r); $l=PayrollLine::where('organization_id',$a->organization_id)->with(['membership.user','period'])->findOrFail($line); abort_unless($l->membership_id===$a->id||app(\App\Services\WorkspaceAccess::class)->allows($a,'payroll.manage'),403); return response(app(\App\Services\PayslipPdfService::class)->render($l),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="payslip-'.$l->id.'.pdf"']); }
    public function export(Request $r): \Symfony\Component\HttpFoundation\StreamedResponse { $a=$this->actor($r); app(\App\Services\WorkspaceAccess::class)->authorize($a,'payroll.manage');$rows=PayrollLine::where('organization_id',$a->organization_id)->with(['membership.user','period'])->latest()->cursor();return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');fputcsv($out,['period','employee','currency','gross','adjustments','net']);foreach($rows as $row)fputcsv($out,[$row->period->period,$row->membership->user->name,$row->currency,$row->gross_amount,$row->adjustment_amount,$row->net_amount]);fclose($out);},'payroll.csv',['Content-Type'=>'text/csv']); }
}
