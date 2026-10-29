<?php
namespace App\Models;
class PayrollLine extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['gross_amount'=>'decimal:2','adjustment_amount'=>'decimal:2','net_amount'=>'decimal:2','snapshot'=>'array']; public function period(){return $this->belongsTo(PayrollPeriod::class,'payroll_period_id');} public function membership(){return $this->belongsTo(OrganizationMembership::class);} public function payments(){return $this->hasMany(PayrollPayment::class,'payroll_line_id');}}
