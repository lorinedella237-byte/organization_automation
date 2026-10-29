<?php
namespace App\Models;
class CompensationAdjustment extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['amount'=>'decimal:2','effective_from'=>'date','effective_to'=>'date']; }
