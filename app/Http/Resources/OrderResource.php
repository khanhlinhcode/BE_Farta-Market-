<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'fullname' => $this->fullname,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'note' => $this->note,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_reference' => $this->payment_reference,
            'payment_expires_at' => $this->payment_expires_at,
            'coupon_id' => $this->coupon_id,
            'discount_amount' => $this->discount_amount,
            'subtotal' => $this->subtotal,
            'shipping_fee' => $this->shipping_fee,
            'grand_total' => $this->grand_total,
            'guest_expires_at' => $this->guest_expires_at,
            'reservation_expires_at' => $this->reservation_expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if (array_key_exists('total', $this->resource->getAttributes())) {
            $data['total'] = $this->resource->getAttribute('total');
        }

        if ($this->resource->relationLoaded('details')) {
            $data['details'] = $this->details->map(fn ($detail) => [
                'id' => $detail->id,
                'order_id' => $detail->order_id,
                'product_id' => $detail->product_id,
                'quantity' => $detail->quantity,
                'unit_price' => $detail->unit_price,
                'product_name' => $detail->product_name,
                'line_total' => $detail->line_total,
                'product' => $detail->relationLoaded('product') && $detail->product
                    ? [
                        'id' => $detail->product->id,
                        'name' => $detail->product->name,
                        'slug' => $detail->product->slug,
                        'img' => $detail->product->img,
                        'price' => $detail->product->price,
                        'inventory' => $detail->product->inventory,
                        'is_active' => $detail->product->is_active,
                        'category' => $detail->product->relationLoaded('category') && $detail->product->category
                            ? [
                                'id' => $detail->product->category->id,
                                'name' => $detail->product->category->name,
                                'slug' => $detail->product->category->slug,
                            ]
                            : null,
                    ]
                    : null,
            ])->values()->all();
        }

        if ($this->resource->relationLoaded('coupon')) {
            $data['coupon'] = $this->coupon
                ? [
                    'id' => $this->coupon->id,
                    'code' => $this->coupon->code,
                    'type' => $this->coupon->type,
                    'value' => $this->coupon->value,
                ]
                : null;
        }

        if ($this->resource->relationLoaded('statusHistory')) {
            $data['status_history'] = $this->statusHistory->map(fn ($history) => [
                'id' => $history->id,
                'order_id' => $history->order_id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'changed_by_id' => $history->changed_by,
                'note' => $history->note,
                'created_at' => $history->created_at,
                'changed_by' => $history->relationLoaded('changedBy') && $history->changedBy
                    ? [
                        'id' => $history->changedBy->id,
                        'name' => $history->changedBy->name,
                        'role' => $history->changedBy->role,
                    ]
                    : null,
            ])->values()->all();
        }

        return $data;
    }
}
