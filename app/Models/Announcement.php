<?php
namespace App\Models;
class Announcement extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['published_at'=>'datetime','expires_at'=>'datetime']; public function author(){return $this->belongsTo(OrganizationMembership::class,'author_membership_id');} }
