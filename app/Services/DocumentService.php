<?php
namespace App\Services;
use App\Models\Document; use App\Models\DocumentVersion; use App\Models\DocumentShare; use App\Models\DocumentLink; use App\Models\Folder; use App\Models\OrganizationMembership; use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Storage; use Illuminate\Support\Str; use Illuminate\Validation\Rule; use Illuminate\Support\Facades\Validator;
class DocumentService
{
    public function __construct(private WorkspaceAccess $access, private AuditRecorder $audit) {}
    public function upload(OrganizationMembership $actor, UploadedFile $file, ?int $folderId=null): Document
    {
        $this->access->authorize($actor,'documents.view',$actor); abort_if($file->getSize()>25*1024*1024,422,'File exceeds the 25 MB limit.');
        $mime=$file->getMimeType() ?: 'application/octet-stream'; $allowed=['application/pdf','image/jpeg','image/png','text/plain','application/vnd.openxmlformats-officedocument.wordprocessingml.document']; abort_unless(in_array($mime,$allowed,true),422,'File type is not allowed.');
        return DB::transaction(function()use($actor,$file,$folderId,$mime){$key=$actor->organization_id.'/'.Str::uuid().'.bin'; Storage::disk('private')->putFileAs(dirname($key),$file,basename($key)); $doc=Document::create(['organization_id'=>$actor->organization_id,'folder_id'=>$folderId,'uploaded_by_membership_id'=>$actor->id,'name'=>$file->getClientOriginalName(),'status'=>'quarantined']); $doc->versions()->create(['organization_id'=>$actor->organization_id,'version'=>1,'storage_key'=>$key,'mime_type'=>$mime,'size'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getRealPath()),'scan_status'=>'pending']); \App\Jobs\ScanDocument::dispatch($doc->id); $this->audit->record($actor->organization_id,$actor,'document.uploaded','document',$doc->id); return $doc->load('versions');});
    }
    public function share(OrganizationMembership $actor, Document $document, array $input): DocumentShare
    {
        abort_unless($document->organization_id === $actor->organization_id, 404); $this->access->authorize($actor, 'files.share', $actor);
        if (isset($input['membership_id']) && ! isset($input['target_type'])) { $input['target_type']='membership'; $input['target_id']=$input['membership_id']; }
        $data=Validator::make($input,['target_type'=>['required',Rule::in(['membership','department','team','organization'])],'target_id'=>['nullable','integer'],'permission'=>['required',Rule::in(['view','download'])]])->validate();
        $columns=['membership_id'=>null,'department_id'=>null,'team_id'=>null];
        if($data['target_type']==='membership')$columns['membership_id']=OrganizationMembership::where('organization_id',$actor->organization_id)->whereKey($data['target_id']??0)->value('id');
        if($data['target_type']==='department')$columns['department_id']=\App\Models\Department::where('organization_id',$actor->organization_id)->whereKey($data['target_id']??0)->value('id');
        if($data['target_type']==='team')$columns['team_id']=\App\Models\Team::where('organization_id',$actor->organization_id)->whereKey($data['target_id']??0)->value('id');
        if($data['target_type']!=='organization')abort_unless(array_filter($columns),422,'The share target is not in this workspace.');
        $share=DocumentShare::create(['organization_id'=>$actor->organization_id,'document_id'=>$document->id,'permission'=>$data['permission']]+$columns); $this->audit->record($actor->organization_id,$actor,'document.shared','document',$document->id); return $share;
    }
    public function createFolder(OrganizationMembership $actor, array $input): Folder
    {
        $this->access->authorize($actor, 'files.share', $actor); $data=Validator::make($input,['name'=>['required','string','max:180'],'parent_id'=>['nullable','integer',Rule::exists('folders','id')->where('organization_id',$actor->organization_id)]])->validate(); return Folder::create($data+['organization_id'=>$actor->organization_id]);
    }
    public function trash(OrganizationMembership $actor, Document $document): void
    {
        abort_unless($document->organization_id===$actor->organization_id,404); $this->access->authorize($actor,'files.share',$actor); $document->update(['trashed_at'=>now(),'status'=>'trashed']); $this->audit->record($actor->organization_id,$actor,'document.trashed','document',$document->id);
    }
    public function restore(OrganizationMembership $actor, Document $document): void
    {
        abort_unless($document->organization_id===$actor->organization_id,404); $this->access->authorize($actor,'files.share',$actor); $document->update(['trashed_at'=>null,'status'=>'quarantined']); $this->audit->record($actor->organization_id,$actor,'document.restored','document',$document->id);
    }
    public function link(OrganizationMembership $actor, Document $document, array $input): DocumentLink
    {
        abort_unless($document->organization_id===$actor->organization_id,404);$this->access->authorize($actor,'files.share',$actor);$data=Validator::make($input,['linkable_type'=>['required',Rule::in(['project','task','daily_activity','report'])],'linkable_id'=>['required','integer']])->validate();$tables=['project'=>'projects','task'=>'tasks','daily_activity'=>'daily_activities','report'=>'reports'];$exists=DB::table($tables[$data['linkable_type']])->where('organization_id',$actor->organization_id)->where('id',$data['linkable_id'])->exists();abort_unless($exists,422,'The linked record is not in this workspace.');return DocumentLink::firstOrCreate(['organization_id'=>$actor->organization_id,'document_id'=>$document->id,'linkable_type'=>$data['linkable_type'],'linkable_id'=>$data['linkable_id']]);
    }
    public function authorizeRead(OrganizationMembership $actor,Document $document):DocumentVersion { abort_unless($document->organization_id===$actor->organization_id && $document->status==='available' && is_null($document->trashed_at),404); $scopes=$this->access->scopes($actor,'documents.view'); $allowed=in_array('organization',$scopes,true)||in_array('own',$scopes,true)&&$document->uploaded_by_membership_id===$actor->id||DocumentShare::where('organization_id',$actor->organization_id)->where('document_id',$document->id)->where(function($q)use($actor){$q->where('membership_id',$actor->id)->orWhere('department_id',$actor->department_id)->orWhere('team_id',$actor->team_id)->orWhere(function($q){$q->whereNull('membership_id')->whereNull('department_id')->whereNull('team_id');});})->exists(); abort_unless($allowed,403); return $document->versions()->where('scan_status','clean')->latest('version')->firstOrFail(); }
}
