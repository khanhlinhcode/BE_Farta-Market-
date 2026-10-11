<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Expire stale online payment orders and restore reserved inventory';

    public function handle(OrderStatusService $statusService): int
    {
        $expiredCount = 0;

        Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->whereIn('payment_method', [Order::PAYMENT_METHOD_SEPAY, Order::PAYMENT_METHOD_VNPAY])
            ->where('payment_status', Order::PAYMENT_STATUS_PENDING)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $orderId) use ($statusService, &$expiredCount) {
                $order = Order::query()
                    ->whereKey($orderId)
                    ->where('status', Order::STATUS_PENDING)
                    ->where('payment_status', Order::PAYMENT_STATUS_PENDING)
                    ->where('payment_expires_at', '<=', now())
                    ->first();

                if (! $order) {
                    return;
                }

                try {
                    $statusService->transition(
                        $order,
                        Order::STATUS_CANCELLED,
                        'Pending online payment expired.'
                    );
                    $expiredCount++;
                } catch (InvalidArgumentException) {
                    // Another process already changed this order.
                }
            });

        $this->info("Expired {$expiredCount} pending online payment order(s).");

        return self::SUCCESS;
    }
}
