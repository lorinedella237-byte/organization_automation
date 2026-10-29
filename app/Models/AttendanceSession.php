<?php
namespace App\Models;
class AttendanceSession extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['clocked_in_at'=>'datetime','clocked_out_at'=>'datetime','local_date'=>'date']; public function membership(){return $this->belongsTo(OrganizationMembership::class);} }
