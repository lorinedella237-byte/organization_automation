<?php
namespace App\Console\Commands;
use App\Models\OrganizationInvitation; use App\Models\Document; use Illuminate\Console\Command;
class PurgeWorkspaceData extends Command
{
    protected $signature='lorine:purge-workspace-data {--days=30}'; protected $description='Remove expired invitations and permanently delete documents trashed beyond the retention period.';
    public function handle(): int { $days=max(1,(int)$this->option('days')); $expired=OrganizationInvitation::whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at','<',now())->update(['revoked_at'=>now()]); $documents=Document::whereNotNull('trashed_at')->where('trashed_at','<',now()->subDays($days))->get(); foreach($documents as $document){foreach($document->versions as $version)\Illuminate\Support\Facades\Storage::disk('private')->delete($version->storage_key);$document->delete();} $this->info("Expired {$expired} invitations and purged {$documents->count()} documents."); return self::SUCCESS; }
}
