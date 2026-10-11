<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationPolicy
{
    /**
     * Lock requested inventory and enforce all reservation limits.
     * Must be called inside the checkout database transaction.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     * @return array{products: Collection<int, Product>, subtotal: int}
     */
    public function lockAndValidate(array $lines, ?int $userId): array
    {
        if ($userId !== null) {
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
        }

        $productIds = collect($lines)->pluck('product_id')->map(fn ($id) => (int) $id)->sort()->values();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $activeReservation = fn ($query) => $query
            ->where('orders.status', Order::STATUS_PENDING)
            ->where(function ($expiry) {
                $expiry->whereNull('orders.reservation_expires_at')
                    ->orWhere('orders.reservation_expires_at', '>', now());
            });

        $subtotal = 0;
        $requestedUnits = 0;

        foreach ($lines as $line) {
            $product = $products->get((int) $line['product_id']);
            $quantity = (int) $line['quantity'];

            if (! $product || ! $product->is_active) {
                $this->reject(422, 'product_unavailable', 'Sản phẩm không khả dụng hoặc đã ngưng kinh doanh.');
            }

            if ($product->inventory < $quantity) {
                $this->reject(422, 'insufficient_inventory', "Sản phẩm {$product->name} không đủ tồn kho.");
            }

            $reservedUnits = (int) DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('order_details.product_id', $product->id)
                ->where($activeReservation)
                ->sum('order_details.quantity');
            $sellableSupply = (int) $product->inventory + $reservedUnits;
            $percentage = $this->positiveConfig('max_unconfirmed_per_sku_percent');
            $skuCap = max(1, (int) ceil($sellableSupply * min($percentage, 100) / 100));

            if ($reservedUnits + $quantity > $skuCap) {
                $this->reject(409, 'reservation_capacity_conflict', 'Sản phẩm đang được giữ cho các đơn chờ xử lý. Vui lòng thử lại sau.');
            }

            if ($sellableSupply <= $this->positiveConfig('low_stock_threshold')) {
                $identityUnits = $userId === null
                    ? $quantity
                    : (int) DB::table('order_details')
                        ->join('orders', 'orders.id', '=', 'order_details.order_id')
                        ->where('orders.user_id', $userId)
                        ->where('order_details.product_id', $product->id)
                        ->where($activeReservation)
                        ->sum('order_details.quantity') + $quantity;

                if ($identityUnits > $this->positiveConfig('low_stock_units_per_identity')) {
                    $this->reject(409, 'reservation_capacity_conflict', 'Sản phẩm tồn kho thấp chỉ được giữ một đơn vị cho mỗi lượt mua.');
                }
            }

            $requestedUnits += $quantity;
            $subtotal += (int) $product->price * $quantity;
        }

        if ($userId !== null) {
            $activeOrders = Order::query()
                ->where('user_id', $userId)
                ->where('status', Order::STATUS_PENDING)
                ->where(function ($expiry) {
                    $expiry->whereNull('reservation_expires_at')
                        ->orWhere('reservation_expires_at', '>', now());
                });
            $pendingCount = (clone $activeOrders)->count();
            $reservedValue = (int) (clone $activeOrders)->sum('subtotal_vnd');
            $reservedUnits = (int) DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.user_id', $userId)
                ->where($activeReservation)
                ->sum('order_details.quantity');

            if ($pendingCount + 1 > $this->positiveConfig('max_pending_orders_per_user')
                || $reservedUnits + $requestedUnits > $this->positiveConfig('max_reserved_units_per_user')
                || $reservedValue + $subtotal > $this->positiveConfig('max_reserved_value_per_user')) {
                $earliestExpiry = (clone $activeOrders)
                    ->whereNotNull('reservation_expires_at')
                    ->min('reservation_expires_at');
                $retryAfter = $earliestExpiry
                    ? max(1, now()->diffInSeconds(CarbonImmutable::parse($earliestExpiry), false))
                    : null;
                $this->reject(
                    429,
                    'reservation_user_limit',
                    'Bạn đã đạt giới hạn đơn hàng đang chờ xử lý. Vui lòng thử lại sau.',
                    $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter]
                );
            }
        }

        return ['products' => $products, 'subtotal' => $subtotal];
    }

    private function positiveConfig(string $key): int
    {
        $value = (int) config("commerce.reservations.{$key}");

        if ($value < 1) {
            throw new \LogicException("Invalid reservation configuration: {$key}");
        }

        return $value;
    }

    /** @param array<string, string> $headers */
    private function reject(int $status, string $code, string $message, array $headers = []): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'error_code' => $code,
        ], $status, $headers));
    }
}
