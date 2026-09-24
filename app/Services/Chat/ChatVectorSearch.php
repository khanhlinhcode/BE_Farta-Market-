<?php

namespace App\Services\Chat;

use App\Models\ChatKnowledgeDocument;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ChatVectorSearch
{
    private bool $sourceIndexEnsured = false;

    public function syncEnabled(): bool
    {
        return (bool) config('services.ai_chat.qdrant_inference_enabled', false);
    }

    /** @return array<int, int> chunk database IDs in rank order */
    public function search(string $query, int $limit = 5): array
    {
        if (! $this->syncEnabled()) {
            throw new RuntimeException('Qdrant Cloud Inference is disabled.');
        }

        $points = $this->client()->post($this->endpoint('/points/query'), [
            'query' => [
                'text' => $query,
                'model' => $this->model(),
            ],
            'limit' => min(20, max(1, $limit)),
            'with_payload' => true,
        ])->throw()->json('result.points', []);

        return collect(is_array($points) ? $points : [])
            ->map(fn ($point) => $point['payload']['chunk_id'] ?? null)
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function syncDocument(ChatKnowledgeDocument $document): int
    {
        if (! $this->syncEnabled()) {
            return 0;
        }

        $this->configuration();
        $this->ensureSourceIndex();
        $this->deleteSource($document->source_id);
        $chunks = $document->chunks()->get();
        foreach ($chunks->chunk(32) as $batch) {
            $points = $batch->map(fn ($chunk) => [
                'id' => (int) $chunk->id,
                'vector' => [
                    'text' => $chunk->content,
                    'model' => $this->model(),
                ],
                'payload' => [
                    'chunk_id' => (int) $chunk->id,
                    'source_id' => $document->source_id,
                    'locale' => $document->locale,
                    'topic' => $document->topic,
                    'checksum' => $chunk->checksum,
                ],
            ])->values()->all();

            $this->client()->put($this->endpoint('/points').'?wait=true', [
                'points' => $points,
            ])->throw();
        }

        return $chunks->count();
    }

    public function deleteSource(string $sourceId): void
    {
        if (! $this->syncEnabled()) {
            return;
        }

        $this->ensureSourceIndex();
        $this->client()->post($this->endpoint('/points/delete').'?wait=true', [
            'filter' => [
                'must' => [[
                    'key' => 'source_id',
                    'match' => ['value' => $sourceId],
                ]],
            ],
        ])->throw();
    }

    private function ensureSourceIndex(): void
    {
        if ($this->sourceIndexEnsured) {
            return;
        }

        $this->client()->put($this->endpoint('/index').'?wait=true', [
            'field_name' => 'source_id',
            'field_schema' => 'keyword',
        ])->throw();
        $this->sourceIndexEnsured = true;
    }

    private function client(): PendingRequest
    {
        $configuration = $this->configuration();

        return Http::acceptJson()
            ->withHeaders(['api-key' => $configuration['key']])
            ->connectTimeout(2)
            ->timeout(15);
    }

    private function endpoint(string $suffix): string
    {
        $configuration = $this->configuration();

        return $configuration['url'].'/collections/'.rawurlencode($configuration['collection']).$suffix;
    }

    private function model(): string
    {
        return $this->configuration()['model'];
    }

    /** @return array{url: string, key: string, collection: string, model: string} */
    private function configuration(): array
    {
        $configuration = [
            'url' => rtrim((string) config('services.ai_chat.qdrant_url'), '/'),
            'key' => (string) config('services.ai_chat.qdrant_key'),
            'collection' => (string) config('services.ai_chat.qdrant_collection'),
            'model' => (string) config('services.ai_chat.qdrant_inference_model'),
        ];
        if (in_array('', $configuration, true)) {
            throw new RuntimeException('Qdrant Cloud Inference is not fully configured.');
        }
        if (! preg_match('/^farta_chat_knowledge(?:_[a-z0-9_-]+)?$/', $configuration['collection'])) {
            throw new RuntimeException('Qdrant collection must be dedicated to Farta chat knowledge.');
        }

        return $configuration;
    }
}
