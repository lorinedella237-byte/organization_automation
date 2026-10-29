<?php
namespace App\Models;
class DocumentShare extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; public function document(){return $this->belongsTo(Document::class);} public function membership(){return $this->belongsTo(OrganizationMembership::class);} }
