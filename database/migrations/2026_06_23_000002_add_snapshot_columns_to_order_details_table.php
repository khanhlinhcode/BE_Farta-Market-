<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->default(0)->after('quantity');
            $table->string('product_name')->default('')->after('unit_price');
            $table->decimal('line_total', 10, 2)->default(0)->after('product_name');
        });

        DB::table('order_details')
            ->orderBy('id')
            ->chunkById(100, function ($details) {
                $products = DB::table('products')
                    ->whereIn('id', $details->pluck('product_id')->all())
                    ->get()
                    ->keyBy('id');

                foreach ($details as $detail) {
                    $product = $products->get($detail->product_id);

                    if (! $product) {
                        continue;
                    }

                    DB::table('order_details')
                        ->where('id', $detail->id)
                        ->update([
                            'unit_price' => $product->price,
                            'product_name' => $product->name,
                            'line_total' => (float) $product->price * (int) $detail->quantity,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'product_name', 'line_total']);
        });
    }
};
