<?php

namespace App\Services;

use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    private const CACHE_TTL = 60;

    public function __construct(
        protected DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function getStats(int $shopId): array
    {
        return Cache::remember("dashboard-stats-{$shopId}", self::CACHE_TTL, function () use ($shopId) {
            $today = Carbon::today();
            $monthStart = Carbon::now()->startOfMonth();

            return [
                'today_sales' => $this->dashboardRepository->getTodaySales($shopId, $today),
                'month_sales' => $this->dashboardRepository->getMonthSales($shopId, $monthStart),
                'pending_orders' => $this->dashboardRepository->getPendingOrders($shopId),
                'total_products' => $this->dashboardRepository->getTotalProducts($shopId),
                'in_stock' => $this->dashboardRepository->getInStockCount($shopId),
                'low_stock_count' => $this->dashboardRepository->getLowStockCount($shopId),
                'pending_purchases' => $this->dashboardRepository->getPendingPurchases($shopId),
                'total_sales' => $this->dashboardRepository->getAllTimeSales($shopId),
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

    public function getNoBoughtProducts(int $shopId, int $limit = 3): array
    {
        return $this->dashboardRepository->getNoBoughtProducts($shopId, $limit);
    }

    public function getAll(int $shopId, int $userId, int $days = 30): array
    {
        return Cache::remember("dashboard-all-{$shopId}-{$days}", self::CACHE_TTL, function () use ($shopId, $userId, $days) {
            $today = Carbon::today();
            $monthStart = Carbon::now()->startOfMonth();

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
                ],
                'category_distribution' => $this->dashboardRepository->getCategoryDistribution($shopId, $userId),
                'category_quantity' => $this->dashboardRepository->getCategoryQuantitySold($shopId, $days),
                'top_products' => $this->dashboardRepository->getTopProducts($shopId, 3),
                'least_products' => $this->dashboardRepository->getLeastProducts($shopId, 3),
                'no_bought_products' => $this->dashboardRepository->getNoBoughtProducts($shopId, 3),
                'recent_sales' => $this->dashboardRepository->getRecentSales($shopId, 5),
            ];
        });
    }
}
