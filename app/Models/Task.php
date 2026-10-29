<?php
namespace App\Models;
class Task extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['due_on' => 'date']; public function project() { return $this->belongsTo(Project::class); } public function milestone() { return $this->belongsTo(Milestone::class); } public function assignee() { return $this->belongsTo(OrganizationMembership::class, 'assignee_membership_id'); } public function checklist() { return $this->hasMany(TaskChecklistItem::class); } public function comments() { return $this->hasMany(TaskComment::class); } }
