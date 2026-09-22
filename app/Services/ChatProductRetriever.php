<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ChatProductRetriever
{
    public const MAX_RESULTS = 5;

    private const QUERY_WORDS = [
        'ban', 'can', 'cho', 'co', 'cua', 'duoc', 'giup', 'goi', 'hang', 'khong', 'la', 'minh',
        'mot', 'nhung', 'phu', 'hop', 'san', 'pham', 'toi', 'tu', 'van', 'voi', 'y',
        'are', 'could', 'for', 'from', 'help', 'me', 'please',
        'recommend', 'shop', 'should', 'some', 'store', 'suggest', 'the', 'you',
    ];

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function retrieve(Collection $products, string $query): Collection
    {
        $terms = array_values(array_diff($this->tokens($query), self::QUERY_WORDS));
        if ($terms === []) {
            return collect();
        }
        $minimumScore = count($terms) > 1 ? 2 : 1;

        return $products->filter(fn (Product $product) => $product->is_active)
            ->map(function (Product $product) use ($terms) {
                $source = $this->source($product);
                $score = 5 * count(array_intersect($terms, $this->tokens($source['name'])))
                    + 3 * count(array_intersect($terms, $this->tokens($source['category'])))
                    + count(array_intersect($terms, $this->tokens($source['summary'])));

                return ['product' => $product, 'score' => $score];
            })
            ->filter(fn (array $result) => $result['score'] >= $minimumScore)
            ->sort(fn (array $a, array $b) => ($b['score'] <=> $a['score'])
                ?: ($a['product']->id <=> $b['product']->id))
            ->take(self::MAX_RESULTS)
            ->pluck('product')
            ->values();
    }

    public function source(Product $product): array
    {
        $summary = preg_replace('/\s+/u', ' ', strip_tags((string) $product->sort_description)) ?? '';

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'category' => (string) ($product->category?->name ?? ''),
            'summary' => mb_substr(trim($summary), 0, 300),
            'price' => (int) $product->price,
            'inventory' => (int) $product->inventory,
        ];
    }

    private function tokens(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower(Str::ascii($text))) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word) => strlen($word) >= 2)));
    }
}
