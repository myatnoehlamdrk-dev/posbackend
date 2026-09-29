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
    public function getNoBoughtProducts(int $shopId, int $limit = 3): array;

    // Row-level tables behind the dashboard "View all" links.
    public function paginateProductsTable(int $shopId, int $userId, int $page = 1, int $perPage = 15, ?string $search = null): LengthAwarePaginator;
    public function paginateStockTable(int $shopId, int $userId, int $page = 1, int $perPage = 15, bool $lowOnly = false, ?string $search = null): LengthAwarePaginator;
    public function paginateSalesTable(int $shopId, int $page = 1, int $perPage = 15, ?string $search = null, ?string $from = null, ?string $to = null): LengthAwarePaginator;
    public function paginateBoughtProductsTable(int $shopId, int $page = 1, int $perPage = 15, string $direction = 'desc', int $days = 30, ?string $search = null): LengthAwarePaginator;
    public function paginateNoBoughtProductsTable(int $shopId, int $page = 1, int $perPage = 15, ?string $search = null): LengthAwarePaginator;
}
