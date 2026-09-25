<?php

use App\Models\ChatKnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('supports forward and rollback migration without changing approved evidence', function () {
    $document = ChatKnowledgeDocument::create([
        'source_id' => 'migration-policy-vi',
        'title' => 'Migration policy',
        'locale' => 'vi',
        'topic' => 'policy',
        'version' => 1,
        'status' => 'published',
        'owner' => 'Farta Market',
        'checksum' => str_repeat('a', 64),
        'source_updated_at' => '2026-09-24',
    ]);
    $chunk = $document->chunks()->create([
        'section' => 'Policy',
        'content' => 'Approved evidence remains unchanged.',
        'normalized_content' => 'approved evidence remains unchanged',
        'retrieval_text' => 'retrieval hint plus approved evidence',
        'position' => 0,
        'checksum' => str_repeat('b', 64),
    ]);
    $migration = require database_path('migrations/2026_09_24_000003_add_retrieval_text_to_chat_knowledge_chunks.php');

    $migration->down();
    expect(Schema::hasColumn('chat_knowledge_chunks', 'retrieval_text'))->toBeFalse()
        ->and($document->chunks()->firstOrFail()->content)->toBe($chunk->content);

    $migration->up();
    expect(Schema::hasColumn('chat_knowledge_chunks', 'retrieval_text'))->toBeTrue()
        ->and($document->chunks()->firstOrFail()->retrieval_text)->toBe($chunk->content)
        ->and($document->chunks()->firstOrFail()->content)->toBe($chunk->content);
});
