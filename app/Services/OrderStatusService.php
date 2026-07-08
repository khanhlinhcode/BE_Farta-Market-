<?php

namespace App\Services;

use App\Mail\OrderDeliveredMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class OrderStatusService
{
    private array $allowedTransitions = [
        Order::STATUS_PENDING => [
            Order::STATUS_CONFIRMED,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_CONFIRMED => [
            Order::STATUS_PROCESSING,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_SHIPPED,
        ],
        Order::STATUS_SHIPPED => [
            Order::STATUS_DELIVERED,
        ],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function allowedNextStatuses(string $status): array
    {
        return $this->allowedTransitions[$status] ?? [];
    }

    public function transition(Order $order, string $newStatus, ?string $note = null): Order
    {
        if ($order->status === $newStatus) {
            return $order;
        }

        if (! in_array($newStatus, $this->allowedNextStatuses($order->status), true)) {
            throw new InvalidArgumentException("Không thể chuyển từ {$order->status} sang {$newStatus}");
        }

        return DB::transaction(function () use ($order, $newStatus, $note) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === $newStatus) {
                return $lockedOrder;
            }

            if (! in_array($newStatus, $this->allowedNextStatuses($lockedOrder->status), true)) {
                throw new InvalidArgumentException("Không thể chuyển từ {$lockedOrder->status} sang {$newStatus}");
            }

            $fromStatus = $lockedOrder->status;

            if ($newStatus === Order::STATUS_CANCELLED) {
                $this->restoreInventory($lockedOrder);
            }

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by' => auth()->id(),
                'note' => $note,
            ]);

            $lockedOrder->update(['status' => $newStatus]);

            if ($newStatus === Order::STATUS_DELIVERED && $lockedOrder->email) {
                Mail::to($lockedOrder->email)->queue(new OrderDeliveredMail($lockedOrder->fresh(['details'])));
            }

            return $lockedOrder->refresh();
        });
    }

    private function restoreInventory(Order $order): void
    {
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
    }
}
