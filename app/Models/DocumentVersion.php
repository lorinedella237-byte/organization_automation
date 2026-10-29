<?php
namespace App\Models;
class DocumentVersion extends \Illuminate\Database\Eloquent\Model { protected $guarded=['id']; protected $casts=['size'=>'integer']; public function document(){return $this->belongsTo(Document::class);} }
