<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

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
                'total' => $this->orderTotal(),
                'trackingUrl' => rtrim((string) config('services.frontend.url'), '/')
                    .'/don-hang-cua-toi',
                'hotline' => '0977-232-232',
            ],
        );
    }

    private function orderTotal(): float
    {
        if ($this->order->relationLoaded('details')) {
            return (float) $this->order->details->sum(
                fn ($detail) => (float) $detail->line_total
            );
        }

        return (float) $this->order->details()->sum('line_total');
    }
}
