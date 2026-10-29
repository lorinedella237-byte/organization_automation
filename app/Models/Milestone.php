<?php
namespace App\Models;
class Milestone extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['due_on' => 'date']; public function project() { return $this->belongsTo(Project::class); } public function tasks() { return $this->hasMany(Task::class); } }
