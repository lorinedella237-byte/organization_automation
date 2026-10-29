<?php
namespace App\Models;
class AttendanceCorrectionRequest extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['requested_clocked_in_at'=>'datetime','requested_clocked_out_at'=>'datetime']; public function membership(){return $this->belongsTo(OrganizationMembership::class);} public function session(){return $this->belongsTo(AttendanceSession::class,'attendance_session_id');} }
