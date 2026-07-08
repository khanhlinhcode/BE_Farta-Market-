<?php

namespace App\Support;

class IdempotencyHasher
{
    public static function hash(array $data): string
    {
        $items = collect($data['products'] ?? $data['items'] ?? [])
            ->map(fn (array $item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])
            ->sortBy('product_id')
            ->values()
            ->toArray();

        return hash('sha256', json_encode([
            'items' => $items,
            'address' => trim((string) ($data['address'] ?? '')),
            'phone' => trim((string) ($data['customer_phone'] ?? $data['phone'] ?? '')),
            'customer_name' => trim((string) ($data['customer_name'] ?? $data['fullname'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'note' => trim((string) ($data['note'] ?? '')),
            'coupon_code' => strtoupper(trim((string) ($data['coupon_code'] ?? ''))),
            'payment_method' => trim((string) ($data['payment_method'] ?? '')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
