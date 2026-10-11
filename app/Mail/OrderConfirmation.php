<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Vnd;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Xác nhận đơn hàng #{$this->order->id} — Farta Market",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'order' => $this->order,
                'subtotal' => $this->subtotal(),
                'discount' => Vnd::fromDecimal($this->order->discount_amount ?? 0),
                'shipping' => Vnd::fromDecimal($this->order->shipping_fee ?? 0),
                'grandTotal' => $this->grandTotal(),
                'trackingUrl' => rtrim((string) config('services.frontend.url'), '/')
                    .'/don-hang-cua-toi',
                'hotline' => '0977-232-232',
            ],
        );
    }

    private function subtotal(): int
    {
        if ($this->order->subtotal !== null) {
            return Vnd::fromDecimal($this->order->subtotal);
        }

        if ($this->order->relationLoaded('details')) {
            return $this->order->details->sum(
                fn ($detail): int => Vnd::fromDecimal($detail->line_total)
            );
        }

        return (int) $this->order->details()->sum('line_total_vnd');
    }

    private function grandTotal(): int
    {
        if ($this->order->grand_total !== null) {
            return Vnd::fromDecimal($this->order->grand_total);
        }

        return $this->subtotal()
            + Vnd::fromDecimal($this->order->shipping_fee ?? 0)
            - Vnd::fromDecimal($this->order->discount_amount ?? 0);
    }
}
