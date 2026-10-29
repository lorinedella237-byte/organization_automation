<?php
namespace App\Models;
class LeaveBalance extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['allocated_days'=>'decimal:2','reserved_days'=>'decimal:2','used_days'=>'decimal:2']; public function membership(){return $this->belongsTo(OrganizationMembership::class);} public function leaveType(){return $this->belongsTo(LeaveType::class);}}
