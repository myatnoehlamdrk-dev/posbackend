<?php

namespace App\Services;

use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    private const CACHE_TTL = 60;
    private const REVISION_KEY = 'dashboard-stock-revision-v1';

    public function __construct(
        protected DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public static function bust(): void
    {
        Cache::put(self::REVISION_KEY, (int) Cache::get(self::REVISION_KEY, 0) + 1);
    }

    private function revision(): int
    {
        return (int) Cache::get(self::REVISION_KEY, 0);
    }

    public function getStats(int $shopId, int $userId): array
    {
        return Cache::remember("dashboard-stats-{$shopId}-{$userId}-{$this->revision()}", self::CACHE_TTL, function () use ($shopId, $userId) {
            $today = Carbon::today();
            $monthStart = Carbon::now()->startOfMonth();
            $stockTiers = $this->dashboardRepository->getStockTierCounts($shopId, $userId);
            $recentAverages = $this->dashboardRepository->getRecentSalesAverages($shopId);

            return [
                'today_sales' => $this->dashboardRepository->getTodaySales($shopId, $today),
                'month_sales' => $this->dashboardRepository->getMonthSales($shopId, $monthStart),
                'pending_orders' => $this->dashboardRepository->getPendingOrders($shopId),
                'total_products' => $this->dashboardRepository->getTotalProducts($shopId, $userId),
                'in_stock' => $this->dashboardRepository->getInStockCount($shopId, $userId),
                'low_stock_count' => $this->dashboardRepository->getLowStockCount($shopId, $userId),
                'pending_purchases' => $this->dashboardRepository->getPendingPurchases($shopId),
                'total_sales' => $this->dashboardRepository->getAllTimeSales($shopId),
                'total_categories' => $this->dashboardRepository->getTotalCategories($shopId, $userId),
                'total_packages' => $this->dashboardRepository->getTotalPackages($shopId, $userId),
                'brand_count' => $this->dashboardRepository->getBrandCount($shopId, $userId),
                'categoryless_products' => $this->dashboardRepository->getCategorylessProductCount($shopId, $userId),
                'high_stock_count' => $stockTiers['high'],
                'mid_stock_count' => $stockTiers['mid'],
                'out_stock_count' => $stockTiers['out'],
                'in_cart_count' => $this->dashboardRepository->getInCartCount($shopId),
                'sales_count' => $this->dashboardRepository->getSalesCount($shopId),
                'avg_sale_last10' => $recentAverages['avg_total'],
                'avg_products_last10' => $recentAverages['avg_products'],
            ];
        });
    }

    public function getSalesChart(int $shopId, int $days = 7): array
    {
        return $this->dashboardRepository->getSalesChart($shopId, $days);
    }

    public function getTopProducts(int $shopId, int $limit = 5): array
    {
        return $this->dashboardRepository->getTopProducts($shopId, $limit);
    }

    public function getRecentSales(int $shopId, int $limit = 5)
    {
        return $this->dashboardRepository->getRecentSales($shopId, $limit);
    }

    public function getCategoryTrend(int $shopId, int $days = 30): array
    {
        return $this->dashboardRepository->getCategoryTrend($shopId, $days);
    }

    public function getCategoryDistribution(int $shopId, int $userId): array
    {
        return $this->dashboardRepository->getCategoryDistribution($shopId, $userId);
    }

    public function getCategoryQuantitySold(int $shopId, int $days = 30): array
    {
        return $this->dashboardRepository->getCategoryQuantitySold($shopId, $days);
    }

    public function getSalesYears(int $shopId): array
    {
        return $this->dashboardRepository->getSalesYears($shopId);
    }

    public function getMonthlySales(int $shopId, int $year): array
    {
        return $this->dashboardRepository->getMonthlySales($shopId, $year);
    }

    public function getLeastProducts(int $shopId, int $limit = 3): array
    {
        return $this->dashboardRepository->getLeastProducts($shopId, $limit);
    }

    public function getNoBoughtProducts(int $shopId, int $userId, int $limit = 3): array
    {
        return $this->dashboardRepository->getNoBoughtProducts($shopId, $userId, $limit);
    }

    public function paginateProductsTable(int $shopId, int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->dashboardRepository->paginateProductsTable($shopId, $userId, $page, $perPage);
    }

    public function paginateStockTable(int $shopId, int $userId, int $page, int $perPage, bool $lowOnly): LengthAwarePaginator
    {
        return $this->dashboardRepository->paginateStockTable($shopId, $userId, $page, $perPage, $lowOnly);
    }

    public function paginateSalesTable(int $shopId, int $page, int $perPage, ?string $month): LengthAwarePaginator
    {
        return $this->dashboardRepository->paginateSalesTable($shopId, $page, $perPage, $month);
    }

    public function paginateBoughtProductsTable(int $shopId, int $userId, int $page, int $perPage, string $direction): LengthAwarePaginator
    {
        return $this->dashboardRepository->paginateBoughtProductsTable($shopId, $userId, $page, $perPage, $direction);
    }

    public function paginateNoBoughtProductsTable(int $shopId, int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->dashboardRepository->paginateNoBoughtProductsTable($shopId, $userId, $page, $perPage);
    }

    public function getAll(int $shopId, int $userId, int $days = 30): array
    {
        return Cache::remember("dashboard-all-{$shopId}-{$userId}-{$days}-{$this->revision()}", self::CACHE_TTL, function () use ($shopId, $userId, $days) {
            $today = Carbon::today();
            $monthStart = Carbon::now()->startOfMonth();
            $stockTiers = $this->dashboardRepository->getStockTierCounts($shopId, $userId);
            $recentAverages = $this->dashboardRepository->getRecentSalesAverages($shopId);

            return [
                'stats' => [
                    'today_sales' => $this->dashboardRepository->getTodaySales($shopId, $today),
                    'month_sales' => $this->dashboardRepository->getMonthSales($shopId, $monthStart),
                    'pending_orders' => $this->dashboardRepository->getPendingOrders($shopId),
                    'total_products' => $this->dashboardRepository->getTotalProducts($shopId, $userId),
                    'in_stock' => $this->dashboardRepository->getInStockCount($shopId, $userId),
                    'low_stock_count' => $this->dashboardRepository->getLowStockCount($shopId, $userId),
                    'pending_purchases' => $this->dashboardRepository->getPendingPurchases($shopId),
                    'total_sales' => $this->dashboardRepository->getAllTimeSales($shopId),
                    'total_categories' => $this->dashboardRepository->getTotalCategories($shopId, $userId),
                    'total_packages' => $this->dashboardRepository->getTotalPackages($shopId, $userId),
                    'brand_count' => $this->dashboardRepository->getBrandCount($shopId, $userId),
                    'categoryless_products' => $this->dashboardRepository->getCategorylessProductCount($shopId, $userId),
                    'high_stock_count' => $stockTiers['high'],
                    'mid_stock_count' => $stockTiers['mid'],
                    'out_stock_count' => $stockTiers['out'],
                    'in_cart_count' => $this->dashboardRepository->getInCartCount($shopId),
                    'sales_count' => $this->dashboardRepository->getSalesCount($shopId),
                    'avg_sale_last10' => $recentAverages['avg_total'],
                    'avg_products_last10' => $recentAverages['avg_products'],
                ],
                'category_distribution' => $this->dashboardRepository->getCategoryDistribution($shopId, $userId),
                'category_quantity' => $this->dashboardRepository->getCategoryQuantitySold($shopId, $days),
                'top_products' => $this->dashboardRepository->getTopProducts($shopId, 3),
                'least_products' => $this->dashboardRepository->getLeastProducts($shopId, 3),
                'no_bought_products' => $this->dashboardRepository->getNoBoughtProducts($shopId, $userId, 3),
                'recent_sales' => $this->dashboardRepository->getRecentSales($shopId, 5),
            ];
        });
    }
}
