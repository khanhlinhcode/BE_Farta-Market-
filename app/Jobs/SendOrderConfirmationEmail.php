<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with(['details.product.category'])
            ->withSum('details as total', 'line_total')
            ->find($this->orderId);

        if (! $order || ! $order->email) {
            return;
        }

        Mail::to($order->email)->send(new OrderConfirmation($order));
    }
}
