<?php
namespace App\Models;
class Document extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['trashed_at'=>'datetime']; public function versions(){return $this->hasMany(DocumentVersion::class);} public function folder(){return $this->belongsTo(Folder::class);} public function uploadedBy(){return $this->belongsTo(OrganizationMembership::class,'uploaded_by_membership_id');} }
