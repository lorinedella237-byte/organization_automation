<?php

namespace Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationInfrastructureTest extends TestCase
{
    public function test_database_jobs_wait_for_commit_and_are_consumed_by_worker(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $queue = Queue::connection('database');

        DB::beginTransaction();
        $queue->push(new FoundationProbeJob(), '', 'foundation-tests');
        $this->assertSame(0, $queue->size('foundation-tests'));
        DB::rollBack();
        $this->assertSame(0, $queue->size('foundation-tests'));

        DB::beginTransaction();
        $queue->push(new FoundationProbeJob(), '', 'foundation-tests');
        $this->assertSame(0, $queue->size('foundation-tests'));
        DB::commit();
        $this->assertSame(1, $queue->size('foundation-tests'));

        $this->artisan('queue:work', [
            'connection' => 'database', '--queue' => 'foundation-tests',
            '--once' => true, '--tries' => 1, '--sleep' => 0,
        ])->assertExitCode(0);
        $this->assertTrue(Cache::get('foundation-job-ran'));
        $this->assertSame(0, $queue->size('foundation-tests'));
    }

    public function test_private_disk_is_outside_public_storage_and_round_trips_files(): void
    {
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.private.root'));
        $this->assertSame('private', config('filesystems.disks.private.visibility'));
        $this->assertFalse(config('filesystems.disks.private.serve'));
        $this->assertNotContains(storage_path('app/private'), config('filesystems.links'));

        $key = 'foundation-check-'.Str::uuid().'.txt';
        try {
            Storage::disk('private')->put($key, 'test private content');
            $this->assertSame('test private content', Storage::disk('private')->get($key));
            $this->assertFileDoesNotExist(public_path('storage/'.$key));
        } finally {
            Storage::disk('private')->delete($key);
        }
    }
}

class FoundationProbeJob implements ShouldQueue
{
    public function handle(): void
    {
        Cache::put('foundation-job-ran', true);
    }
}
