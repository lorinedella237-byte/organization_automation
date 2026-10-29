<?php
namespace App\Models;
class LeaveType extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['requires_reason'=>'boolean','is_paid'=>'boolean','is_active'=>'boolean']; }
