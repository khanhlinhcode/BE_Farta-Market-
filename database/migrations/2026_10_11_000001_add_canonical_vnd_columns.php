<?php

use App\Support\Vnd;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal_vnd')->nullable()->after('subtotal');
            $table->unsignedBigInteger('shipping_fee_vnd')->nullable()->after('shipping_fee');
            $table->unsignedBigInteger('discount_amount_vnd')->nullable()->after('discount_amount');
            $table->unsignedBigInteger('grand_total_vnd')->nullable()->after('grand_total');
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_vnd')->nullable()->after('unit_price');
            $table->unsignedBigInteger('line_total_vnd')->nullable()->after('line_total');
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('value_vnd')->nullable()->after('value');
            $table->unsignedBigInteger('min_order_amount_vnd')->nullable()->after('min_order_amount');
            $table->unsignedBigInteger('max_discount_amount_vnd')->nullable()->after('max_discount_amount');
        });
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_amount_vnd')->nullable()->after('discount_amount');
        });

        $this->backfill('orders', [
            'subtotal' => 'subtotal_vnd',
            'shipping_fee' => 'shipping_fee_vnd',
            'discount_amount' => 'discount_amount_vnd',
            'grand_total' => 'grand_total_vnd',
        ]);
        $this->backfill('order_details', [
            'unit_price' => 'unit_price_vnd',
            'line_total' => 'line_total_vnd',
        ]);
        $this->backfill('coupon_usages', ['discount_amount' => 'discount_amount_vnd']);
        $this->backfill('coupons', [
            'min_order_amount' => 'min_order_amount_vnd',
            'max_discount_amount' => 'max_discount_amount_vnd',
        ]);

        DB::table('coupons')->where('type', 'fixed')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('coupons')->where('id', $row->id)->update([
                    'value_vnd' => Vnd::fromDecimal((string) $row->value),
                ]);
            }
        });

        foreach (['orders', 'order_details', 'coupon_usages'] as $table) {
            $columns = match ($table) {
                'orders' => ['subtotal_vnd', 'shipping_fee_vnd', 'discount_amount_vnd', 'grand_total_vnd'],
                'order_details' => ['unit_price_vnd', 'line_total_vnd'],
                default => ['discount_amount_vnd'],
            };
            foreach ($columns as $column) {
                if (DB::table($table)->whereNull($column)->exists()) {
                    throw new LogicException("Canonical VND backfill failed for {$table}.{$column}.");
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('coupon_usages', fn (Blueprint $table) => $table->dropColumn('discount_amount_vnd'));
        Schema::table('coupons', fn (Blueprint $table) => $table->dropColumn([
            'value_vnd', 'min_order_amount_vnd', 'max_discount_amount_vnd',
        ]));
        Schema::table('order_details', fn (Blueprint $table) => $table->dropColumn([
            'unit_price_vnd', 'line_total_vnd',
        ]));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'subtotal_vnd', 'shipping_fee_vnd', 'discount_amount_vnd', 'grand_total_vnd',
        ]));
    }

    /** @param array<string, string> $columns */
    private function backfill(string $table, array $columns): void
    {
        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns): void {
            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $legacy => $canonical) {
                    $value = $row->{$legacy};
                    $updates[$canonical] = $value === null ? null : Vnd::fromDecimal((string) $value);
                }
                DB::table($table)->where('id', $row->id)->update($updates);
            }
        });
    }
};
