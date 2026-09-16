<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->default(0)->after('payment_status');
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('subtotal');
            $table->decimal('grand_total', 10, 2)->default(0)->after('shipping_fee');
        });

        DB::table('orders')->orderBy('id')->chunk(100, function ($orders) {
            foreach ($orders as $order) {
                $subtotal = (float) DB::table('order_details')
                    ->where('order_id', $order->id)
                    ->sum('line_total');
                $shippingFee = $subtotal > 0 && $subtotal < 200000 ? 20000 : 0;

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'subtotal' => $subtotal,
                        'shipping_fee' => $shippingFee,
                        'grand_total' => $subtotal + $shippingFee,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'shipping_fee', 'grand_total']);
        });
    }
};
