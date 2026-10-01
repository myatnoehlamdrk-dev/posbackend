<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface DashboardRepositoryInterface
{
    public function getTodaySales(int $shopId, Carbon $today): array;
    public function getMonthSales(int $shopId, Carbon $monthStart): array;
    public function getPendingOrders(int $shopId): int;
    public function getTotalProducts(int $shopId, int $userId): int;
    public function getInStockCount(int $shopId, int $userId): int;
    public function getLowStockCount(int $shopId, int $userId): int;
    public function getPendingPurchases(int $shopId): int;
    public function getAllTimeSales(int $shopId): int;
    public function getSalesChart(int $shopId, int $days = 7): array;
    public function getTopProducts(int $shopId, int $limit = 5): array;
    public function getRecentSales(int $shopId, int $limit = 5): Collection;
    public function getCategoryTrend(int $shopId, int $days = 30): array;
    public function getCategoryDistribution(int $shopId, int $userId): array;
    public function getCategoryQuantitySold(int $shopId, int $days = 30): array;
    public function getSalesYears(int $shopId): array;
    public function getMonthlySales(int $shopId, int $year): array;
    public function getLeastProducts(int $shopId, int $limit = 3): array;
    public function getNoBoughtProducts(int $shopId, int $userId, int $limit = 3): array;

    // Catalog, stock-tier and cart/sales summaries that back the extra rows of
    // KPI cards on the dashboard.
    public function getTotalCategories(int $shopId, int $userId): int;
    public function getTotalPackages(int $shopId, int $userId): int;
    public function getBrandCount(int $shopId, int $userId): int;
    public function getCategorylessProductCount(int $shopId, int $userId): int;

    /** @return array{high:int,mid:int,low:int,out:int} */
    public function getStockTierCounts(int $shopId, int $userId): array;

    public function getInCartCount(int $shopId): int;
    public function getSalesCount(int $shopId): int;

    /** @return array{avg_total:int,avg_products:int} */
    public function getRecentSalesAverages(int $shopId, int $limit = 10): array;

    // Row-level tables behind the dashboard "View all" links. All of these are
    // server-paginated; the client never asks for more than one page at a time.
    public function paginateProductsTable(int $shopId, int $userId, int $page = 1, int $perPage = 15): LengthAwarePaginator;
    public function paginateStockTable(int $shopId, int $userId, int $page = 1, int $perPage = 15, bool $lowOnly = false): LengthAwarePaginator;
    public function paginateSalesTable(int $shopId, int $page = 1, int $perPage = 15, ?string $month = null): LengthAwarePaginator;
    public function paginateBoughtProductsTable(int $shopId, int $userId, int $page = 1, int $perPage = 15, string $direction = 'desc'): LengthAwarePaginator;
    public function paginateNoBoughtProductsTable(int $shopId, int $userId, int $page = 1, int $perPage = 15): LengthAwarePaginator;
}
