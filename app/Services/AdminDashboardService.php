<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function getStats(): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $totalSales = Sale::selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')->first();
        $todaySales = Sale::where('created_at', '>=', $today)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')
            ->first();
        $monthSales = Sale::where('created_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')
            ->first();

        return [
            'total_shops' => Shop::count(),
            'active_shops' => Shop::where('is_active', true)->count(),
            'total_users' => User::count(),
            'pending_approvals' => User::where('active_status', false)->where('is_verified', true)->count(),
            'total_sales' => $totalSales->count,
            'total_revenue' => (int) $totalSales->total,
            'today_sales_count' => $todaySales->count,
            'today_revenue' => (int) $todaySales->total,
            'month_sales_count' => $monthSales->count,
            'month_revenue' => (int) $monthSales->total,
        ];
    }

    public function getSalesChart(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        return Sale::where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(grand_total) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function getTopProducts(int $limit = 10): array
    {
        return DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->join('shops', 'users.shop_id', '=', 'shops.id')
            ->where('sale_items.created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                'products.name as product_name',
                'products.image as product_image',
                'shops.shop_name as shop_name',
                DB::raw('ROUND(AVG(sale_items.unit_price)) as unit_price'),
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue'),
                DB::raw('MAX(products.stock) as stock')
            )
            ->groupBy('products.name', 'products.image', 'shops.shop_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getShopsWithStats(): array
    {
        $shops = Shop::withCount('users')->get();

        return $shops->map(function ($shop) {
            $sales = Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shop->id))
                ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(grand_total), 0) as total_revenue')
                ->first();

            return [
                'id' => (string) $shop->id,
                'shop_name' => $shop->shop_name,
                'shop_type' => $shop->shop_type,
                'shop_image' => $shop->shop_image,
                'is_active' => $shop->is_active,
                'owner_name' => $shop->owner_name,
                'owner_email' => $shop->owner_email,
                'users_count' => $shop->users_count,
                'sales_count' => $sales->sales_count,
                'total_revenue' => (int) $sales->total_revenue,
                'created_at' => $shop->created_at->toIso8601String(),
            ];
        })->toArray();
    }
}
