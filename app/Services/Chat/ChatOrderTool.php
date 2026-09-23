<?php

namespace App\Services\Chat;

use App\Models\Order;
use App\Models\User;

final class ChatOrderTool
{
    /** @return array{status: string, order?: array<string, mixed>} */
    public function getCustomerOrder(?User $user, int $orderId): array
    {
        if (! $user) {
            return ['status' => 'auth_required'];
        }
        if ($user->role !== 'customer') {
            return ['status' => 'customer_only'];
        }

        $order = Order::query()
            ->where('user_id', $user->getKey())
            ->whereKey($orderId)
            ->first();

        return $order
            ? ['status' => 'ok', 'order' => $this->serialize($order)]
            : ['status' => 'not_found'];
    }

    /** @return array{status: string, order?: array<string, mixed>} */
    public function getCustomerRecentOrder(?User $user): array
    {
        if (! $user) {
            return ['status' => 'auth_required'];
        }
        if ($user->role !== 'customer') {
            return ['status' => 'customer_only'];
        }

        $order = Order::query()
            ->where('user_id', $user->getKey())
            ->latest('created_at')
            ->latest('id')
            ->first();

        return $order
            ? ['status' => 'ok', 'order' => $this->serialize($order)]
            : ['status' => 'not_found'];
    }

    /** @return array{id: int, status: string, payment_status: string, payment_method: string, grand_total: int, created_at: ?string} */
    private function serialize(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'status' => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
            'payment_method' => (string) $order->payment_method,
            'grand_total' => (int) round((float) $order->grand_total),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
