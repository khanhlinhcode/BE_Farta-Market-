<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminSystemController extends Controller
{
    public function queueHealth()
    {
        $pendingJobs = Schema::hasTable('jobs')
            ? (int) DB::table('jobs')->count()
            : 0;
        $failedJobs = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : 0;
        $oldestPendingCreatedAt = Schema::hasTable('jobs')
            ? DB::table('jobs')->min('created_at')
            : null;

        return response()->json([
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'oldest_pending_age_seconds' => $oldestPendingCreatedAt
                ? max(0, now()->timestamp - (int) $oldestPendingCreatedAt)
                : 0,
        ]);
    }
}
