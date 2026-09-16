<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DashboardRevenueQuery
{
    public function deliveredOrders()
    {
        return Order::query()->where('status', Order::STATUS_DELIVERED);
    }

    public function total(): float
    {
        return (float) $this->deliveredOrders()->sum('grand_total');
    }

    public function today(): float
    {
        return (float) $this->deliveredOrders()
            ->whereDate('created_at', now()->toDateString())
            ->sum('grand_total');
    }

    public function currentMonth(): float
    {
        return (float) $this->deliveredOrders()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('grand_total');
    }

    public function chart(string $range): array
    {
        if ($range === '12m') {
            return $this->monthlyChart();
        }

        return $this->dailyChart($range === '30d' ? 29 : 6);
    }

    private function dailyChart(int $days): array
    {
        return $this->deliveredOrders()
            ->selectRaw('DATE(orders.created_at) as date, COUNT(*) as orders, COALESCE(SUM(orders.grand_total), 0) as revenue')
            ->where('orders.created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'orders' => (int) $item->orders,
                'revenue' => (float) $item->revenue,
            ])
            ->all();
    }

    private function monthlyChart(): array
    {
        $monthBucket = DB::getDriverName() === 'mysql'
            ? "DATE_FORMAT(orders.created_at, '%Y-%m')"
            : "strftime('%Y-%m', orders.created_at)";

        return $this->deliveredOrders()
            ->selectRaw("{$monthBucket} as date, COUNT(*) as orders, COALESCE(SUM(orders.grand_total), 0) as revenue")
            ->where('orders.created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy(DB::raw($monthBucket))
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'orders' => (int) $item->orders,
                'revenue' => (float) $item->revenue,
            ])
            ->all();
    }
}
