<?php

namespace App\Services\Chat;

use App\Models\Product;
use Illuminate\Support\Collection;

/** Resolves untrusted product mentions to current active database identities. */
final class ChatEntityCanonicalizer
{
    public function __construct(private readonly ChatEntityExtractor $extractor) {}

    public function resolve(ChatRouteFrame $route): ChatRouteFrame
    {
        if ($route->branches !== []) {
            $route = $route->withBranches(array_map(
                fn (ChatRouteFrame $branch): ChatRouteFrame => $this->resolve($branch),
                $route->branches,
            ));
        }

        $products = Product::query()->where('is_active', true)->get(['id', 'name']);
        $resolved = $this->fromContext($route, $products);
        if ($resolved === []) {
            $resolved = $this->fromMentions($route, $products);
        }
        if ($resolved === []) {
            return $route;
        }

        $names = array_values(array_map(fn (Product $product): string => (string) $product->name, $resolved));
        $ids = array_values(array_map(fn (Product $product): int => (int) $product->id, $resolved));

        return $route->withEntities([
            ...$route->entities,
            'canonical_product' => count($names) === 1 ? $names[0] : $names,
            'canonical_product_id' => count($ids) === 1 ? $ids[0] : null,
            'canonical_product_ids' => $ids,
        ]);
    }

    /** @param Collection<int, Product> $products @return array<int, Product> */
    private function fromContext(ChatRouteFrame $route, Collection $products): array
    {
        $ids = collect($route->entities['context_product_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_int($id) && $id > 0)->values()->all();
        if ($ids === [] && is_int($route->contextProductId)) {
            $ids = [$route->contextProductId];
        }
        if ($ids === []) {
            return [];
        }

        $byId = $products->keyBy('id');

        return collect($ids)->map(fn (int $id) => $byId->get($id))->filter()->values()->all();
    }

    /** @param Collection<int, Product> $products @return array<int, Product> */
    private function fromMentions(ChatRouteFrame $route, Collection $products): array
    {
        $mention = trim((string) ($route->entities['product_name'] ?? ''));
        $source = $route->semanticIntent === 'product_search' || $mention === '' ? $route->query : $mention;
        $candidates = $this->extractor->canonicalProductMentions($source);
        if ($candidates === [] && $mention !== '') {
            $candidates = [$this->extractor->normalize($mention)];
        }
        if ($candidates === []) {
            return [];
        }

        $byName = $products->keyBy(fn (Product $product): string => $this->extractor->normalize($product->name));

        return collect($candidates)->map(fn (string $candidate) => $byName->get($candidate))
            ->filter()->unique('id')->values()->all();
    }
}
