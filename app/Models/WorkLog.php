<?php
namespace App\Models;
class WorkLog extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['work_date' => 'date', 'approved_at' => 'datetime']; public function membership() { return $this->belongsTo(OrganizationMembership::class); } public function project() { return $this->belongsTo(Project::class); } public function task() { return $this->belongsTo(Task::class); } }
