<?php
namespace App\Models;
class TaskComment extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; public function task() { return $this->belongsTo(Task::class); } public function membership() { return $this->belongsTo(OrganizationMembership::class); } }
