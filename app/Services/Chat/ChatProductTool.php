<?php

namespace App\Services\Chat;

use App\Models\Product;
use App\Services\ChatProductRetriever;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ChatProductTool
{
    public const MAX_CANDIDATES = 20;

    public const MAX_OUTPUT = 5;

    public function __construct(private readonly ChatProductRetriever $retriever) {}

    /**
     * Read-only product search. The query builder applies deterministic business filters;
     * the existing lexical retriever ranks only the bounded candidate set.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, Product>
     *
     * @throws ValidationException
     */
    public function search(array $input): Collection
    {
        $validated = Validator::make($input, [
            'query' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'in_stock' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_OUTPUT],
        ])->validate();
        if (($validated['min_price'] ?? null) !== null
            && ($validated['max_price'] ?? null) !== null
            && $validated['max_price'] < $validated['min_price']) {
            throw ValidationException::withMessages(['max_price' => 'The maximum price must be greater than or equal to the minimum price.']);
        }

        $query = Product::query()
            ->with('category:id,name')
            ->select(['id', 'slug', 'name', 'img', 'price', 'inventory', 'is_active', 'category_id', 'sort_description'])
            ->where('is_active', true);

        if (($validated['category'] ?? null) !== null) {
            $category = trim((string) $validated['category']);
            $query->whereHas('category', fn ($builder) => $builder->where('name', 'like', '%'.$category.'%'));
        }
        if (($validated['min_price'] ?? null) !== null) {
            $query->where('price', '>=', $validated['min_price']);
        }
        if (($validated['max_price'] ?? null) !== null) {
            $query->where('price', '<=', $validated['max_price']);
        }
        if (($validated['in_stock'] ?? null) === true) {
            $query->where('inventory', '>', 0);
        }

        $candidates = $query->orderBy('name')->limit(self::MAX_CANDIDATES)->get();
        $text = trim((string) ($validated['query'] ?? ''));
        $ranked = $text === '' ? $candidates : $this->retriever->retrieve($candidates, $text);
        $hasStructuredFilter = collect(['category', 'min_price', 'max_price', 'in_stock'])
            ->contains(fn (string $key) => ($validated[$key] ?? null) !== null);
        if ($ranked->isEmpty() && $hasStructuredFilter) {
            $ranked = $candidates;
        }

        return $ranked->take((int) ($validated['limit'] ?? self::MAX_OUTPUT))->values();
    }

    /** @param array<int, int> $ids @return Collection<int, Product> */
    public function reloadVerified(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => is_int($id) && $id > 0)));
        if ($ids === [] || count($ids) > self::MAX_OUTPUT) {
            return collect();
        }

        $products = Product::query()->with('category:id,name')
            ->where('is_active', true)->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $products->get($id))->filter()->values();
    }

    /** @return array{id: int, slug: ?string, name: string, price: int, inventory: int, inventory_status: string, image_url: ?string, category: ?array{id: int, name: string}} */
    public function card(Product $product): array
    {
        $inventory = max(0, (int) $product->inventory);

        return [
            'id' => (int) $product->id,
            'slug' => $product->slug,
            'name' => (string) $product->name,
            'price' => (int) $product->price,
            'inventory' => $inventory,
            'inventory_status' => $inventory > 0 ? 'in_stock' : 'out_of_stock',
            'image_url' => $product->img,
            'category' => $product->category ? [
                'id' => (int) $product->category->id,
                'name' => (string) $product->category->name,
            ] : null,
        ];
    }
}
