<?php
namespace App\Jobs;
use App\Models\Document; use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels; use Illuminate\Support\Facades\Storage;
class ScanDocument implements ShouldQueue
{
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public int $tries=3; public function __construct(public int $documentId){$this->afterCommit();}
 public function handle():void { $document=Document::with('versions')->find($this->documentId); if(!$document)return; $version=$document->versions->sortByDesc('version')->first(); if(!$version)return; $disk=Storage::disk('private'); if(!$disk->exists($version->storage_key)){ $version->update(['scan_status'=>'failed']); return; } $scanner=(string)config('filesystems.private_scanner_binary',''); if($scanner===''){ $version->update(['scan_status'=>'quarantined']); return; } $command=escapeshellarg($scanner).' --no-summary '.escapeshellarg($disk->path($version->storage_key)); exec($command.' 2>&1',$output,$exit); if($exit!==0){$version->update(['scan_status'=>'failed']);return;} $version->update(['scan_status'=>'clean']); $document->update(['status'=>'available']); }
}
