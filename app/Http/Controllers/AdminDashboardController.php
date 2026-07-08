<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\DashboardRevenueQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminDashboardController extends Controller
{
    public function __construct(private DashboardRevenueQuery $revenueQuery)
    {
    }

    public function __invoke()
    {
        $summary = $this->summaryData();

        return response()->json([
            'total_revenue' => $this->revenueQuery->total(),
            'today_revenue' => $summary['revenue_today'],
            'total_orders' => Order::count(),
            'new_orders' => $summary['orders_today'],
            'pending_orders' => $summary['orders_pending_count'],
            'revenue_today' => $summary['revenue_today'],
            'revenue_this_month' => $summary['revenue_this_month'],
            'orders_today' => $summary['orders_today'],
            'orders_pending_count' => $summary['orders_pending_count'],
            'low_stock_products' => $summary['low_stock_products'],
            'top_products' => $this->topProductsData(5),
            'latest_orders' => Order::with(['details.product.category'])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
            'orders_by_day' => $this->revenueChartData('7d'),
            'revenue_chart' => $this->revenueChartData('7d'),
        ]);
    }

    public function summary()
    {
        return response()->json($this->summaryData());
    }

    public function revenueChart(Request $request)
    {
        $data = $request->validate([
            'range' => ['nullable', Rule::in(['7d', '30d', '12m'])],
        ]);

        return response()->json($this->revenueChartData($data['range'] ?? '7d'));
    }

    public function topProducts(Request $request)
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($this->topProductsData((int) ($data['limit'] ?? 5)));
    }

    private function summaryData(): array
    {
        return [
            'revenue_today' => $this->revenueQuery->today(),
            'revenue_this_month' => $this->revenueQuery->currentMonth(),
            'orders_today' => Order::whereDate('created_at', now()->toDateString())->count(),
            'orders_pending_count' => Order::where('status', Order::STATUS_PENDING)->count(),
            'low_stock_products' => Product::with('category')
                ->where('inventory', '<', 10)
                ->orderBy('inventory')
                ->limit(8)
                ->get(['id', 'name', 'inventory', 'category_id']),
        ];
    }

    private function revenueChartData(string $range): array
    {
        return $this->revenueQuery->chart($range);
    }

    private function topProductsData(int $limit): array
    {
        return DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->selectRaw('order_details.product_id, COALESCE(products.name, order_details.product_name) as product_name, SUM(order_details.quantity) as quantity_sold, SUM(order_details.line_total) as revenue')
            ->groupBy('order_details.product_id', 'products.name', 'order_details.product_name')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity_sold' => (int) $item->quantity_sold,
                'revenue' => (float) $item->revenue,
            ])
            ->all();
    }
}
