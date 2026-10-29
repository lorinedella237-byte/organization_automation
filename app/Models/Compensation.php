<?php
namespace App\Models;
class Compensation extends \Illuminate\Database\Eloquent\Model { protected $table='compensations'; protected $guarded=['id']; protected $casts=['base_amount'=>'decimal:2','effective_from'=>'date','effective_to'=>'date']; }
