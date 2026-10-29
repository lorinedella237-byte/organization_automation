<?php
namespace App\Models;
class OrganizationHoliday extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['holiday_date'=>'date']; }
