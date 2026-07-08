<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderDeliveredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Đơn hàng #{$this->order->id} đã giao thành công — Farta Market",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-delivered',
            with: [
                'order' => $this->order,
                'trackingUrl' => rtrim((string) config('services.frontend.url'), '/')
                    .'/don-hang-cua-toi',
            ],
        );
    }
}
