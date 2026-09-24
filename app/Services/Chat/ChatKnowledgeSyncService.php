<?php

namespace App\Services\Chat;

use App\Models\ChatKnowledgeDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ChatKnowledgeSyncService
{
    public function __construct(private readonly ChatVectorSearch $vectorSearch) {}

    /** @return array{files: int, indexed: int, unchanged: int, skipped: int, vector_synced: int, vector_deleted: int} */
    public function sync(string $directory, bool $dryRun = false): array
    {
        $stats = [
            'files' => 0,
            'indexed' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'vector_synced' => 0,
            'vector_deleted' => 0,
        ];
        foreach (glob(rtrim($directory, '/').'/*.json') ?: [] as $path) {
            $stats['files']++;
            $data = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            $validated = $this->validate($data, basename($path));
            if ($validated === null) {
                $stats['skipped']++;
                $sourceId = is_array($data) ? ($data['source_id'] ?? null) : null;
                if (! $dryRun && is_string($sourceId) && preg_match('/^[a-z0-9][a-z0-9-]{2,119}$/', $sourceId)) {
                    $existing = ChatKnowledgeDocument::query()->where('source_id', $sourceId)->first();
                    if ($existing) {
                        $chunkCount = $existing->chunks()->count();
                        $this->vectorSearch->deleteSource($existing->source_id);
                        $stats['vector_deleted'] += $chunkCount;
                        DB::transaction(function () use ($existing): void {
                            $existing->chunks()->delete();
                            $existing->update(['status' => 'draft']);
                        });
                    }
                }

                continue;
            }

            $checksum = hash('sha256', json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $existing = ChatKnowledgeDocument::query()->where('source_id', $validated['source_id'])->first();
            if ($existing?->checksum === $checksum) {
                $stats['unchanged']++;
                if (! $dryRun) {
                    $stats['vector_synced'] += $this->vectorSearch->syncDocument($existing);
                }

                continue;
            }
            $stats['indexed']++;
            if ($dryRun) {
                continue;
            }

            $document = DB::transaction(function () use ($validated, $checksum): ChatKnowledgeDocument {
                $document = ChatKnowledgeDocument::query()->updateOrCreate(
                    ['source_id' => $validated['source_id']],
                    [
                        'title' => $validated['title'],
                        'locale' => $validated['locale'],
                        'topic' => $validated['topic'],
                        'version' => $validated['version'],
                        'status' => 'published',
                        'owner' => $validated['owner'],
                        'checksum' => $checksum,
                        'source_updated_at' => $validated['updated_at'],
                    ]
                );
                $document->chunks()->delete();
                foreach ($this->chunks($validated['sections']) as $position => $chunk) {
                    $document->chunks()->create([
                        'section' => $chunk['heading'],
                        'content' => $chunk['content'],
                        'normalized_content' => $this->normalize($chunk['content']),
                        'position' => $position,
                        'checksum' => hash('sha256', $chunk['heading']."\n".$chunk['content']),
                    ]);
                }

                return $document;
            });
            $stats['vector_synced'] += $this->vectorSearch->syncDocument($document);
        }

        return $stats;
    }

    /** @param mixed $data @return array<string, mixed>|null */
    private function validate(mixed $data, string $filename): ?array
    {
        if (! is_array($data)) {
            throw new RuntimeException("{$filename}: document must be a JSON object.");
        }
        foreach (['source_id', 'title', 'locale', 'topic', 'status', 'updated_at', 'owner', 'sections'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new RuntimeException("{$filename}: missing {$key}.");
            }
        }
        if ($data['status'] !== 'published') {
            return null;
        }
        if (! preg_match('/^[a-z0-9][a-z0-9-]{2,119}$/', (string) $data['source_id'])) {
            throw new RuntimeException("{$filename}: source_id is invalid.");
        }
        if (! in_array($data['locale'], ['vi', 'en'], true) || ! is_array($data['sections']) || $data['sections'] === []) {
            throw new RuntimeException("{$filename}: locale or sections are invalid.");
        }
        $version = filter_var($data['version'] ?? 1, FILTER_VALIDATE_INT);
        if ($version === false || $version < 1) {
            throw new RuntimeException("{$filename}: version must be a positive integer.");
        }
        foreach (['title', 'topic', 'owner'] as $key) {
            if (! is_string($data[$key]) || trim($data[$key]) === '') {
                throw new RuntimeException("{$filename}: {$key} is invalid.");
            }
        }
        foreach ($data['sections'] as $section) {
            if (! is_array($section) || ! is_string($section['heading'] ?? null) || ! is_string($section['content'] ?? null)
                || trim($section['heading']) === '' || trim($section['content']) === '') {
                throw new RuntimeException("{$filename}: every section needs heading and content.");
            }
            $text = $section['heading'].' '.$section['content'];
            if (preg_match('/\b(?:TODO|CHANGEME)\b|\{\{.*?\}\}|<\/?system>|\[system\]|ignore (?:all )?previous|bo qua (?:moi )?huong dan/i', Str::ascii($text))) {
                throw new RuntimeException("{$filename}: placeholder or instruction-like content is not allowed.");
            }
        }

        return [
            'source_id' => $data['source_id'],
            'title' => trim($data['title']),
            'locale' => $data['locale'],
            'topic' => trim($data['topic']),
            'version' => (int) $version,
            'status' => 'published',
            'updated_at' => $data['updated_at'],
            'owner' => trim($data['owner']),
            'sections' => $data['sections'],
        ];
    }

    /** @param array<int, array{heading: string, content: string}> $sections @return array<int, array{heading: string, content: string}> */
    private function chunks(array $sections): array
    {
        $chunks = [];
        foreach ($sections as $section) {
            $sentences = preg_split('/(?<=[.!?])\s+(?=\p{Lu}|\d)/u', trim($section['content'])) ?: [];
            $current = '';
            foreach ($sentences as $sentence) {
                if ($current !== '' && mb_strlen($current.' '.$sentence) > 900) {
                    $chunks[] = ['heading' => trim($section['heading']), 'content' => $current];
                    $current = '';
                }
                $current = trim($current.' '.$sentence);
            }
            if ($current !== '') {
                $chunks[] = ['heading' => trim($section['heading']), 'content' => $current];
            }
        }

        return $chunks;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }
}
