<?php
namespace App\Models;
class Folder extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; public function children(){return $this->hasMany(Folder::class,'parent_id');} public function documents(){return $this->hasMany(Document::class);} }
