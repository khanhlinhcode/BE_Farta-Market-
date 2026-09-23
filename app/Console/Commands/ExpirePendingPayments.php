<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire-pending {--minutes=30 : Pending payment age in minutes before expiration}';

    protected $description = 'Expire stale online payment orders and restore reserved inventory';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);
        $expiredCount = 0;

        Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->whereIn('payment_method', [Order::PAYMENT_METHOD_SEPAY, Order::PAYMENT_METHOD_VNPAY])
            ->where('payment_status', Order::PAYMENT_STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $orderId) use ($cutoff, &$expiredCount) {
                if ($this->expireOrder($orderId, $cutoff)) {
                    $expiredCount++;
                }
            });

        $this->info("Expired {$expiredCount} pending online payment order(s).");

        return self::SUCCESS;
    }

    private function expireOrder(int $orderId, $cutoff): bool
    {
        return DB::transaction(function () use ($orderId, $cutoff) {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (
                ! $order
                || $order->status !== Order::STATUS_PENDING
                || ! in_array($order->payment_method, [Order::PAYMENT_METHOD_SEPAY, Order::PAYMENT_METHOD_VNPAY], true)
                || $order->payment_status !== Order::PAYMENT_STATUS_PENDING
                || ! $order->created_at
                || $order->created_at->greaterThanOrEqualTo($cutoff)
            ) {
                return false;
            }

            $details = $order->details()->get();
            $products = Product::query()
                ->whereIn('id', $details->pluck('product_id')->filter()->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($details as $detail) {
                $products->get($detail->product_id)?->increment('inventory', $detail->quantity);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => Order::STATUS_CANCELLED,
                'changed_by' => null,
                'note' => 'Pending online payment expired.',
            ]);

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'payment_status' => Order::PAYMENT_STATUS_FAILED,
            ]);

            return true;
        });
    }
}
