<?php
namespace App\Models;
class ReportVersion extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['snapshot' => 'array', 'submitted_at' => 'datetime']; public function report() { return $this->belongsTo(Report::class); } }
