<?php
namespace App\Models;
class PayrollPayment extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['amount'=>'decimal:2','paid_on'=>'date']; public function line(){return $this->belongsTo(PayrollLine::class,'payroll_line_id');} }
