<?php
namespace App\Http\Controllers;
use App\Models\Document; use App\Models\OrganizationMembership; use App\Services\DocumentService; use Illuminate\Http\Request; use Illuminate\Support\Facades\Storage;
class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}
    private function actor(Request $r): OrganizationMembership { return $r->attributes->get('currentMembership'); }
    public function index(Request $r) { $a=$this->actor($r); $docs=Document::where('organization_id',$a->organization_id)->whereNull('trashed_at')->with('versions')->latest()->paginate(50); return response()->json($docs); }
    public function store(Request $r) { $r->validate(['file'=>['required','file','max:25600'],'folder_id'=>['nullable','integer']]); return response()->json(['data'=>$this->documents->upload($this->actor($r),$r->file('file'),$r->integer('folder_id')?:null)],201); }
    public function download(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); $version=$this->documents->authorizeRead($this->actor($r),$doc); return Storage::disk('private')->download($version->storage_key,$doc->name,['Content-Type'=>$version->mime_type]); }
    public function share(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); return response()->json(['data'=>$this->documents->share($this->actor($r),$doc,$r->all())],201); }
    public function folder(Request $r) { return response()->json(['data'=>$this->documents->createFolder($this->actor($r),$r->all())],201); }
    public function trash(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); $this->documents->trash($this->actor($r),$doc); return response()->json(['status'=>'trashed']); }
    public function restore(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); $this->documents->restore($this->actor($r),$doc); return response()->json(['status'=>'restored']); }
    public function link(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); return response()->json(['data'=>$this->documents->link($this->actor($r),$doc,$r->all())],201); }
}
