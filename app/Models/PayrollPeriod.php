<?php
namespace App\Models;
class PayrollPeriod extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['starts_on'=>'date','ends_on'=>'date']; public function lines(){return $this->hasMany(PayrollLine::class);} }
