<?php

namespace App\Console\Commands;

use App\Models\AnalyticsPageView;
use Illuminate\Console\Command;

class PruneAnalytics extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Delete expired privacy-safe analytics page views';

    public function handle(): int
    {
        $days = max(1, (int) config('services.analytics.retention_days', 90));
        $deleted = AnalyticsPageView::query()->where('occurred_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} expired analytics page views.");

        return self::SUCCESS;
    }
}
