<?php
namespace App\Models;
class Report extends \Illuminate\Database\Eloquent\Model { protected $guarded = ['id']; protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime']; public function template() { return $this->belongsTo(ReportTemplate::class); } public function membership() { return $this->belongsTo(OrganizationMembership::class); } public function versions() { return $this->hasMany(ReportVersion::class); } public function reviews() { return $this->hasMany(ReportReview::class); } }
