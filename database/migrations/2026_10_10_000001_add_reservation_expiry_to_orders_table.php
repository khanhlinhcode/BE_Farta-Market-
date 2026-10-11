<?php

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reservation_expires_at')->nullable()->index()->after('guest_expires_at');
            $table->index(['user_id', 'status', 'reservation_expires_at'], 'orders_user_reservation_index');
        });

        DB::table('orders')
            ->where('status', Order::STATUS_PENDING)
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $ttl = match ($order->payment_method) {
                        Order::PAYMENT_METHOD_SEPAY, Order::PAYMENT_METHOD_VNPAY => max(1, (int) config('commerce.reservations.sepay_ttl_minutes')),
                        Order::PAYMENT_METHOD_COD => $order->user_id === null
                            ? max(1, (int) config('commerce.reservations.guest_cod_ttl_minutes'))
                            : max(1, (int) config('commerce.reservations.authenticated_cod_ttl_minutes')),
                        default => null,
                    };

                    if ($ttl === null) {
                        continue;
                    }

                    $expiry = $order->payment_expires_at
                        ?? $order->guest_expires_at
                        ?? CarbonImmutable::parse($order->created_at)->addMinutes($ttl);

                    DB::table('orders')->where('id', $order->id)->update([
                        'reservation_expires_at' => $expiry,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_reservation_index');
            $table->dropIndex(['reservation_expires_at']);
            $table->dropColumn('reservation_expires_at');
        });
    }
};
