<?php
namespace App\Console\Commands;
use App\Models\AttendanceSession; use Illuminate\Console\Command;
class MarkStaleAttendance extends Command
{
    protected $signature='lorine:mark-stale-attendance {--hours=24}'; protected $description='Mark open attendance sessions as incomplete after the configured safety window.';
    public function handle(): int { $count=AttendanceSession::whereNull('clocked_out_at')->where('clocked_in_at','<',now()->subHours(max(1,(int)$this->option('hours'))))->update(['status'=>'incomplete']); $this->info("Marked {$count} attendance sessions incomplete."); return self::SUCCESS; }
}
