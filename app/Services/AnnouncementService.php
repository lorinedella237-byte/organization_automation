<?php
namespace App\Services;
use App\Models\Announcement; use App\Models\OrganizationMembership; use Illuminate\Support\Facades\Validator; use Illuminate\Validation\Rule;
class AnnouncementService
{
 public function __construct(private WorkspaceAccess $access, private AuditRecorder $audit){}
 public function create(OrganizationMembership $actor,array $input):Announcement{$this->access->authorize($actor,'announcements.manage');$data=Validator::make($input,['title'=>['required','string','max:180'],'body'=>['required','string','max:20000'],'published_at'=>['nullable','date'],'expires_at'=>['nullable','date','after:published_at']])->validate();$a=Announcement::create($data+['organization_id'=>$actor->organization_id,'author_membership_id'=>$actor->id,'status'=>isset($data['published_at'])?'published':'draft']);$this->audit->record($actor->organization_id,$actor,'announcement.created','announcement',$a->id);return $a;}
 public function publish(OrganizationMembership $actor,Announcement $announcement):Announcement{abort_unless($announcement->organization_id===$actor->organization_id,404);$this->access->authorize($actor,'announcements.manage');$announcement->update(['status'=>'published','published_at'=>$announcement->published_at??now()]);$this->audit->record($actor->organization_id,$actor,'announcement.published','announcement',$announcement->id);return $announcement;}
 public function visible(OrganizationMembership $actor){$this->access->authorize($actor,'announcements.view',$actor);return Announcement::where('organization_id',$actor->organization_id)->where('status','published')->where('published_at','<=',now())->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->with('author.user')->latest('published_at');}
}
