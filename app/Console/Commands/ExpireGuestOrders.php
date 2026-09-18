<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ExpireGuestOrders extends Command
{
    protected $signature = 'orders:expire-guests';

    protected $description = 'Cancel stale guest COD orders and restore reserved inventory';

    public function handle(OrderStatusService $statusService): int
    {
        $expired = 0;

        Order::query()
            ->whereNull('user_id')
            ->where('status', Order::STATUS_PENDING)
            ->where('payment_method', Order::PAYMENT_METHOD_COD)
            ->whereNotNull('guest_expires_at')
            ->where('guest_expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $orderId) use ($statusService, &$expired) {
                $order = Order::query()->find($orderId);
                if (! $order) {
                    return;
                }

                try {
                    $statusService->transition(
                        $order,
                        Order::STATUS_CANCELLED,
                        'Unconfirmed guest order expired.'
                    );
                    $expired++;
                } catch (InvalidArgumentException) {
                    // Another process already changed this order.
                }
            });

        $this->info("Expired {$expired} guest order(s).");

        return self::SUCCESS;
    }
}
