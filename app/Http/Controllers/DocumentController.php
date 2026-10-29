<?php
namespace App\Http\Controllers;
use App\Models\Document; use App\Models\OrganizationMembership; use App\Services\DocumentService; use Illuminate\Http\Request; use Illuminate\Support\Facades\Storage;
class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}
    private function actor(Request $r): OrganizationMembership { return $r->attributes->get('currentMembership'); }
    public function index(Request $r)
    {
        $a = $this->actor($r);
        // Claude, 2026-10-29: the list named every document in the
        // organization to anyone who asked, while download was checked. A
        // file list is itself confidential, so it now runs the same rule.
        $docs = $this->documents->visibleDocuments($a)->with('versions')->latest()->paginate(50);
        if ($r->expectsJson()) {
            return response()->json($docs);
        }

        return view('workspace.documents.index', [
            'page' => $docs,
            'folders' => \App\Models\Folder::where('organization_id', $a->organization_id)->orderBy('name')->pluck('name', 'id'),
        ]);
    }
    public function store(Request $r) { $r->validate(['file'=>['required','file','max:25600'],'folder_id'=>['nullable','integer']]); $doc=$this->documents->upload($this->actor($r),$r->file('file'),$r->integer('folder_id')?:null); return $r->expectsJson()?response()->json(['data'=>$doc],201):redirect()->route('documents.index')->with('success','File uploaded.'); }
    public function download(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); $version=$this->documents->authorizeRead($this->actor($r),$doc); return Storage::disk('private')->download($version->storage_key,$doc->name,['Content-Type'=>$version->mime_type]); }
    public function share(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); $share=$this->documents->share($this->actor($r),$doc,$r->all()); return $r->expectsJson()?response()->json(['data'=>$share],201):redirect()->route('documents.index')->with('success','Access granted.'); }
    public function folder(Request $r) { $folder=$this->documents->createFolder($this->actor($r),$r->all()); return $r->expectsJson()?response()->json(['data'=>$folder],201):redirect()->route('documents.index')->with('success','Folder created.'); }
    public function trash(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); $this->documents->trash($this->actor($r),$doc); return $r->expectsJson()?response()->json(['status'=>'trashed']):redirect()->route('documents.index')->with('success','Moved to trash.'); }
    public function restore(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); $this->documents->restore($this->actor($r),$doc); return $r->expectsJson()?response()->json(['status'=>'restored']):redirect()->route('documents.index')->with('success','Restored.'); }
    public function link(Request $r,int $document) { $doc=Document::where('organization_id',$this->actor($r)->organization_id)->findOrFail($document); return response()->json(['data'=>$this->documents->link($this->actor($r),$doc,$r->all())],201); }
}
