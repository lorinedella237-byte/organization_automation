<?php
namespace App\Services;
use App\Models\OrganizationHoliday; use App\Models\OrganizationMembership; use App\Models\WorkCalendar; use Illuminate\Support\Facades\Validator; use Illuminate\Validation\Rule;
class WorkCalendarService
{
 public function __construct(private WorkspaceAccess $access,private AuditRecorder $audit){}
 public function save(OrganizationMembership $actor,array $input,?WorkCalendar $calendar=null):WorkCalendar{$this->access->authorize($actor,'organization.manage');$data=Validator::make($input,['name'=>['required','string','max:120'],'timezone'=>['required','timezone'],'weekly_schedule'=>['required','array'],'grace_minutes'=>['required','integer','min:0','max:240'],'cutoff_time'=>['nullable','date_format:H:i'],'is_default'=>['sometimes','boolean']])->validate();$calendar??=new WorkCalendar(['organization_id'=>$actor->organization_id]);$calendar->fill($data);$calendar->save();if($calendar->is_default)WorkCalendar::where('organization_id',$actor->organization_id)->whereKeyNot($calendar->id)->update(['is_default'=>false]);$this->audit->record($actor->organization_id,$actor,'work_calendar.saved','work_calendar',$calendar->id);return $calendar;}
 public function holiday(OrganizationMembership $actor,array $input):OrganizationHoliday{$this->access->authorize($actor,'organization.manage');$data=Validator::make($input,['holiday_date'=>['required','date_format:Y-m-d'],'name'=>['required','string','max:160']])->validate();return OrganizationHoliday::create($data+['organization_id'=>$actor->organization_id]);}
}
