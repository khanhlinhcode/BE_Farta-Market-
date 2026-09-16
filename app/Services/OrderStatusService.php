<?php

namespace App\Services;

use App\Mail\OrderDeliveredMail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class OrderStatusService
{
    private array $allowedTransitions = [
        Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
        Order::STATUS_CONFIRMED => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
        Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED],
        Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function allowedNextStatuses(string $status): array
    {
        return $this->allowedTransitions[$status] ?? [];
    }

    public function transition(Order $order, string $status, ?string $note = null, ?int $customerId = null): Order
    {
        return DB::transaction(function () use ($order, $status, $note, $customerId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $actor = request()->user();
            if ($customerId !== null
                && ($order->user_id !== $customerId || $order->status !== Order::STATUS_PENDING || $status !== Order::STATUS_CANCELLED)) {
                throw new InvalidArgumentException('Chỉ có thể hủy đơn hàng đang chờ xử lý.');
            }

            if ($order->status === $status && $customerId === null) {
                return $order;
            }

            if (! in_array($status, $this->allowedNextStatuses($order->status), true)) {
                throw new InvalidArgumentException("Không thể chuyển từ {$order->status} sang {$status}");
            }

            if ($status === Order::STATUS_CONFIRMED && $order->payment_method === Order::PAYMENT_METHOD_VNPAY
                && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                throw new InvalidArgumentException('Đơn VNPay chưa được thanh toán.');
            }

            if ($status === Order::STATUS_CANCELLED) {
                $details = $order->details()->orderBy('product_id')->get();
                $products = Product::query()->whereIn('id', $details->pluck('product_id')->filter())
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                foreach ($details as $detail) {
                    $products->get($detail->product_id)?->increment('inventory', $detail->quantity);
                }

                if ($order->coupon_id) {
                    $coupon = Coupon::query()->lockForUpdate()->find($order->coupon_id);
                    $removed = CouponUsage::query()->where('order_id', $order->id)->delete();
                    if ($coupon && $removed > 0 && $coupon->used_count > 0) {
                        $coupon->decrement('used_count');
                    }
                }
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $status,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);
            $updates = ['status' => $status];
            if ($status === Order::STATUS_CANCELLED
                && $order->payment_method === Order::PAYMENT_METHOD_VNPAY
                && $order->payment_status === Order::PAYMENT_STATUS_PENDING) {
                $updates['payment_status'] = Order::PAYMENT_STATUS_FAILED;
            }
            $order->update($updates);

            if ($status === Order::STATUS_DELIVERED && $order->email) {
                Mail::to($order->email)->queue(new OrderDeliveredMail($order->fresh(['details'])));
            }

            return $order->refresh();
        }, 3);
    }
}
