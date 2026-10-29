<?php
namespace App\Models;
class DocumentLink extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; public function document(){return $this->belongsTo(Document::class);} }
