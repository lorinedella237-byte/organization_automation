<?php
namespace App\Models;
class ReportTemplate extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['requires_attachment' => 'boolean', 'is_active' => 'boolean']; public function reports() { return $this->hasMany(Report::class, 'template_id'); } }
