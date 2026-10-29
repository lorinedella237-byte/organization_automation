<?php
namespace App\Http\Controllers;
use App\Models\OrganizationMembership; use App\Models\Report; use App\Services\ProjectService; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\StreamedResponse;
class ReportController extends Controller
{
 public function __construct(private ProjectService $projects){}
 private function actor(Request $r):OrganizationMembership{return $r->attributes->get('currentMembership');}
 public function index(Request $r)
 {
     $a = $this->actor($r);
     // Claude, 2026-10-29: every submitted report in the organization was
     // listed to every member. Another person's report is now visible only
     // when the reader's reports.view scope covers them.
     $visible = app(\App\Services\WorkspaceAccess::class)->visibleMembers($a, 'reports.view')->select('id');
     $page = Report::where('organization_id', $a->organization_id)
         ->where(function ($q) use ($a, $visible) {
             $q->where('membership_id', $a->id)
                 ->orWhere(function ($q) use ($visible) { $q->whereIn('membership_id', $visible)->whereIn('status', ['submitted', 'returned']); });
         })->with(['template', 'membership.user', 'versions'])->latest()->paginate(50);

     // Claude, 2026-10-29: HTML requests get the server-rendered screen;
        // asynchronous callers still get the identical JSON.
     return $r->expectsJson() ? response()->json($page) : view('workspace.reports.index', ['page' => $page, 'actor' => $a]);
 }
 public function submit(Request $r,int $report){$a=$this->actor($r);$m=Report::where('organization_id',$a->organization_id)->findOrFail($report);$data=$r->validate(['snapshot'=>['required','array']]);$version=$this->projects->submitReport($a,$m,$data['snapshot']);return $r->expectsJson()?response()->json(['data'=>$version],201):redirect()->route('reports.index')->with('success','Report submitted.');}
 public function review(Request $r,int $report){$a=$this->actor($r);$m=Report::where('organization_id',$a->organization_id)->findOrFail($report);$d=$r->validate(['decision'=>['required','in:approved,returned'],'comment'=>['nullable','string','max:2000']]);$review=$this->projects->reviewReport($a,$m,$d['decision'],$d['comment']??null);return $r->expectsJson()?response()->json(['data'=>$review]):redirect()->route('reports.index')->with('success','Report '.$d['decision'].'.');}
 public function pdf(Request $r,int $report){$a=$this->actor($r);$m=Report::where('organization_id',$a->organization_id)->findOrFail($report);abort_unless($m->membership_id===$a->id||app(\App\Services\WorkspaceAccess::class)->allows($a,'reports.view',$m->membership),403);$pdf=app(\App\Services\ReportPdfService::class)->render($m);return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="report-'.$m->id.'.pdf"']);}
 public function export(Request $r):StreamedResponse{$a=$this->actor($r);$rows=Report::where('organization_id',$a->organization_id)->with(['membership.user','template'])->latest()->cursor();return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');fputcsv($out,['id','employee','template','period_start','period_end','status']);foreach($rows as $row)fputcsv($out,[$row->id,$row->membership->user->name,$row->template->name,$row->period_start->toDateString(),$row->period_end->toDateString(),$row->status]);fclose($out);},'reports.csv',['Content-Type'=>'text/csv']);}
}
