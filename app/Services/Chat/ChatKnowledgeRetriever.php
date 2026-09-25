<?php

namespace App\Services\Chat;

use App\Models\ChatKnowledgeChunk;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Throwable;

final class ChatKnowledgeRetriever
{
    public const MAX_EVIDENCE = 5;

    public function __construct(
        private readonly ChatVectorSearch $vectorSearch,
        private readonly ChatProvider $provider,
    ) {}

    /** @return array{chunks: array<int, array<string, mixed>>, queries: array<int, string>, mode: string, vector_fallback: bool} */
    public function retrieve(string $question, string $locale = 'vi'): array
    {
        $queries = $this->queries($question);
        $chunks = $this->candidateChunks($locale);
        $sparse = $this->rankSparse($chunks, $queries);

        if (($sparse[0]['score'] ?? 0) < 4 && config('services.ai_chat.query_expansion_enabled', false)) {
            $queries = array_values(array_unique([...$queries, ...$this->modelQueries($question)]));
            $queries = array_slice($queries, 0, 3);
            $sparse = $this->rankSparse($chunks, $queries);
        }

        $vectorFallback = false;
        $mode = 'sparse';
        $ranked = $sparse;
        if (config('services.ai_chat.vector_search_enabled', false)) {
            try {
                $denseIds = $this->vectorSearch->search($question, 20);
                $ranked = $this->rrf($sparse, $denseIds, $chunks);
                $mode = $denseIds === [] ? 'sparse' : 'hybrid';
            } catch (Throwable) {
                $vectorFallback = true;
            }
        }

        return [
            'chunks' => array_slice(array_map(function (array $item): array {
                unset($item['score']);
                unset($item['search_text']);

                return $item;
            }, $ranked), 0, self::MAX_EVIDENCE),
            'queries' => $queries,
            'mode' => $mode,
            'vector_fallback' => $vectorFallback,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function candidateChunks(string $locale): array
    {
        $settings = SiteSetting::current();
        $shipping = $locale === 'en'
            ? sprintf('Standard shipping costs %s VND. Orders from %s VND receive free shipping.', number_format($settings->shipping_fee), number_format($settings->free_shipping_threshold))
            : sprintf('Phí giao hàng tiêu chuẩn là %sđ. Đơn hàng từ %sđ được miễn phí giao hàng.', number_format($settings->shipping_fee, 0, ',', '.'), number_format($settings->free_shipping_threshold, 0, ',', '.'));
        $contact = $locale === 'en'
            ? "Contact email: {$settings->contact_email}. Customer phone: {$settings->contact_phone}. Support phone: {$settings->support_phone}. Address: {$settings->address_en}."
            : "Email liên hệ: {$settings->contact_email}. Điện thoại khách hàng: {$settings->contact_phone}. Điện thoại hỗ trợ: {$settings->support_phone}. Địa chỉ: {$settings->address_vi}.";
        $dynamic = [
            ['id' => 'setting:shipping', 'db_id' => null, 'source_id' => 'site-settings', 'title' => $locale === 'en' ? 'Shipping information' : 'Thông tin giao hàng', 'section' => $locale === 'en' ? 'Shipping fee' : 'Phí giao hàng', 'topic' => 'shipping', 'content' => $shipping, 'search_text' => $shipping],
            ['id' => 'setting:contact', 'db_id' => null, 'source_id' => 'site-settings', 'title' => $locale === 'en' ? 'Store contact' : 'Liên hệ cửa hàng', 'section' => $locale === 'en' ? 'Contact and address' : 'Liên hệ và địa chỉ', 'topic' => 'contact', 'content' => $contact, 'search_text' => $contact],
        ];
        $stored = ChatKnowledgeChunk::query()->with('document:id,source_id,title,locale,topic,status')
            ->whereHas('document', fn ($query) => $query->where('status', 'published')->whereIn('locale', [$locale, 'vi']))
            ->limit(500)->get()->map(fn (ChatKnowledgeChunk $chunk) => [
                'id' => 'chunk:'.$chunk->id,
                'db_id' => (int) $chunk->id,
                'source_id' => $chunk->document->source_id,
                'title' => $chunk->document->title,
                'section' => $chunk->section,
                'topic' => $chunk->document->topic,
                'content' => $chunk->content,
                'search_text' => $chunk->retrieval_text ?: $chunk->content,
            ])->all();

        return [...$dynamic, ...$stored];
    }

    /** @param array<int, array<string, mixed>> $chunks @param array<int, string> $queries @return array<int, array<string, mixed>> */
    private function rankSparse(array $chunks, array $queries): array
    {
        return collect($chunks)->map(function (array $chunk) use ($queries): array {
            $best = 0;
            foreach ($queries as $query) {
                $terms = $this->tokens($query);
                $title = $this->tokens($chunk['title']);
                $section = $this->tokens($chunk['section']);
                $topic = $this->tokens($chunk['topic']);
                $content = $this->tokens($chunk['search_text']);
                $score = 5 * count(array_intersect($terms, $title))
                    + 4 * count(array_intersect($terms, $section))
                    + 3 * count(array_intersect($terms, $topic))
                    + count(array_intersect($terms, $content));
                $best = max($best, $score);
            }

            return [...$chunk, 'score' => $best];
        })->filter(fn (array $chunk) => $chunk['score'] >= 2)
            ->sortByDesc('score')->values()->all();
    }

    /** @param array<int, array<string, mixed>> $sparse @param array<int, int> $denseIds @param array<int, array<string, mixed>> $chunks @return array<int, array<string, mixed>> */
    private function rrf(array $sparse, array $denseIds, array $chunks): array
    {
        $scores = [];
        $items = collect($chunks)->keyBy('id');
        foreach ($sparse as $rank => $chunk) {
            $scores[$chunk['id']] = ($scores[$chunk['id']] ?? 0) + 1 / (60 + $rank + 1);
        }
        foreach ($denseIds as $rank => $id) {
            $key = 'chunk:'.$id;
            if ($items->has($key)) {
                $scores[$key] = ($scores[$key] ?? 0) + 1 / (60 + $rank + 1);
            }
        }

        arsort($scores);

        return collect(array_keys($scores))->map(function (string $id) use ($items, $scores) {
            $item = $items->get($id);

            return $item ? [...$item, 'score' => $scores[$id]] : null;
        })->filter()->values()->all();
    }

    /** @return array<int, string> */
    private function queries(string $question): array
    {
        $normalized = $this->normalize($question);
        $queries = [$normalized];
        if (preg_match('/\b(phi ship|phi giao|van chuyen|free ship|mien phi|delivery|shipping)\b/', $normalized)) {
            $queries[] = 'phi giao hang mien phi giao hang shipping fee free shipping';
        }
        if (preg_match('/\b(lien he|dia chi|so dien thoai|hotline|email|contact|address|phone)\b/', $normalized)) {
            $queries[] = 'lien he dia chi dien thoai email contact address phone';
        }
        if (preg_match('/\b(doi tra|hoan tra|return|refund|bao quan|storage|thanh toan|payment|mua hang|how to order)\b/', $normalized)) {
            $queries[] = 'chinh sach huong dan policy return payment storage order';
        }

        return array_slice(array_values(array_unique($queries)), 0, 3);
    }

    /** @return array<int, string> */
    private function modelQueries(string $question): array
    {
        try {
            $schema = [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => ['queries' => ['type' => 'array', 'maxItems' => 2, 'items' => ['type' => 'string']]],
                'required' => ['queries'],
            ];
            $raw = $this->provider->structured(
                [['role' => 'user', 'content' => $question]],
                'Create at most two short search paraphrases. Preserve names, numbers, order codes and constraints. Return JSON only.',
                $schema,
                'knowledge_query_expansion'
            );
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);

            return collect($decoded['queries'] ?? [])->filter(fn ($query) => is_string($query) && mb_strlen($query) <= 500)
                ->map(fn ($query) => $this->normalize($query))->take(2)->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int, string> */
    private function tokens(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', $this->normalize($text)) ?: [];
        $stop = ['la', 'co', 'cho', 'toi', 'minh', 'shop', 'cua', 'bao', 'nhieu', 'how', 'much', 'the', 'is', 'are'];

        return array_values(array_unique(array_diff(array_filter($words, fn ($word) => strlen($word) >= 2), $stop)));
    }

    private function normalize(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?: $value;
        }

        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }
}
