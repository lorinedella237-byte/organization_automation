<?php
namespace App\Models;
class WorkCalendar extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['weekly_schedule'=>'array','is_default'=>'boolean']; }
