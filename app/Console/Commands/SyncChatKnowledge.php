<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatKnowledgeSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncChatKnowledge extends Command
{
    protected $signature = 'chat:knowledge:sync {--dry-run : Validate and report changes without writing}';

    protected $description = 'Validate and index published Farta Market chat knowledge documents';

    public function handle(ChatKnowledgeSyncService $sync): int
    {
        try {
            $stats = $sync->sync(resource_path('chat/knowledge'), (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s: %d file(s), %d to index, %d unchanged, %d draft/skipped, %d vector chunk(s) synced, %d deleted.',
            $this->option('dry-run') ? 'Dry run complete' : 'Sync complete',
            $stats['files'], $stats['indexed'], $stats['unchanged'], $stats['skipped'],
            $stats['vector_synced'], $stats['vector_deleted']
        ));

        return self::SUCCESS;
    }
}
