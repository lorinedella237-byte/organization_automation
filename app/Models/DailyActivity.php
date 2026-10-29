<?php
namespace App\Models;
class DailyActivity extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['activity_date' => 'date']; public function membership() { return $this->belongsTo(OrganizationMembership::class); } public function project() { return $this->belongsTo(Project::class); } public function task() { return $this->belongsTo(Task::class); } }
