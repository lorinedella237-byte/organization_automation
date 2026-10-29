<?php
namespace App\Models;
class LeaveRequest extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['starts_on'=>'date','ends_on'=>'date','days'=>'decimal:2']; public function membership(){return $this->belongsTo(OrganizationMembership::class);} public function leaveType(){return $this->belongsTo(LeaveType::class);} public function approvals(){return $this->hasMany(LeaveApproval::class);} }
