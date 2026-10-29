<?php
namespace App\Models;
class LeaveApproval extends \Illuminate\Database\Eloquent\Model { public $timestamps=false; protected $guarded=['id']; protected $casts=['created_at'=>'datetime']; }
