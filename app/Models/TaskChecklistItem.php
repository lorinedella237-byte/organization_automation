<?php
namespace App\Models;
class TaskChecklistItem extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['completed' => 'boolean', 'completed_at' => 'datetime']; public function task() { return $this->belongsTo(Task::class); } }
