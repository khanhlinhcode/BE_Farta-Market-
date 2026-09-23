<?php

namespace App\Services\Chat;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ChatCartTool
{
    public const MAX_ITEMS = 20;

    public function __construct(private readonly ChatProductTool $products) {}

    /**
     * Client cart data is only a list of references. Every commerce field is reloaded.
     *
     * @param  array<int, array<string, mixed>>  $cart
     * @return array{items: array<int, array<string, mixed>>, total_quantity: int, unavailable_count: int}
     *
     * @throws ValidationException
     */
    public function resolve(array $cart): array
    {
        $validated = Validator::make(['cart' => $cart], [
            'cart' => ['array', 'max:'.self::MAX_ITEMS],
            'cart.*' => ['array:product_id,quantity'],
            'cart.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ])->validate()['cart'];

        $ids = array_map(fn (array $item) => (int) $item['product_id'], $validated);
        $records = Product::query()->with('category:id,name')->whereIn('id', $ids)->get()->keyBy('id');
        $items = [];
        $unavailable = 0;

        foreach ($validated as $reference) {
            $id = (int) $reference['product_id'];
            $quantity = (int) $reference['quantity'];
            $product = $records->get($id);
            $available = $product && $product->is_active && (int) $product->inventory >= $quantity;

            if (! $available) {
                $unavailable++;
            }

            $items[] = [
                'product_id' => $id,
                'quantity' => $quantity,
                'available' => $available,
                'product' => $product && $product->is_active ? $this->products->card($product) : null,
            ];
        }

        return [
            'items' => $items,
            'total_quantity' => array_sum(array_column($validated, 'quantity')),
            'unavailable_count' => $unavailable,
        ];
    }
}
