<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CheckStuckQueueJobs extends Command
{
    protected $signature = 'queue:check-stuck';

    protected $description = 'Report queue jobs that have been pending too long';

    public function handle(): int
    {
        $pendingJobs = Schema::hasTable('jobs')
            ? (int) DB::table('jobs')->count()
            : 0;
        $oldestPendingCreatedAt = Schema::hasTable('jobs')
            ? DB::table('jobs')->min('created_at')
            : null;
        $oldestPendingAge = $oldestPendingCreatedAt
            ? max(0, now()->timestamp - (int) $oldestPendingCreatedAt)
            : 0;

        if ($pendingJobs > 0 && $oldestPendingAge > 300) {
            Log::critical('Queue worker có thể đã dừng, có job kẹt quá 5 phút', [
                'pending_jobs' => $pendingJobs,
                'oldest_pending_age_seconds' => $oldestPendingAge,
            ]);
        }

        $this->info("Queue health: {$pendingJobs} pending job(s), oldest pending age {$oldestPendingAge}s.");

        return self::SUCCESS;
    }
}
