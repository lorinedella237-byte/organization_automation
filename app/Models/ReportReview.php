<?php
namespace App\Models;
class ReportReview extends \Illuminate\Database\Eloquent\Model { public $timestamps = false; protected $guarded = ['id']; protected $casts = ['created_at' => 'datetime']; public function report() { return $this->belongsTo(Report::class); } }
