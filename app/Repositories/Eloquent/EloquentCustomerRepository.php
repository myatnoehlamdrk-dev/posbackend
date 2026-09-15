<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Sale;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    private const WALKIN_NAMES = ['', 'customer', 'walk_in'];

    public function __construct(
        protected Customer $model,
    ) {}

    private function scopeShop($query, int $shopId)
    {
        return $query->whereHas('user', fn ($q) => $q->where('shop_id', $shopId));
    }

    private function isWalkIn(?string $name): bool
    {
        if ($name === null || trim($name) === '') return true;
        return in_array(strtolower(trim($name)), self::WALKIN_NAMES);
    }

    public function listForShop(Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $query = $this->model->where('shop_id', $user->shop_id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate(20);
    }

    public function findById(int $id): ?Customer
    {
        return $this->model->find($id);
    }

    public function create(array $data): Customer
    {
        return $this->model->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function delete(Customer $customer): bool
    {
        return $customer->delete();
    }

    public function analytics(int $shopId): array
    {
        $sales = $this->scopeShop(Sale::query(), $shopId)
            ->get();

        $nonWalkInSales = $sales->filter(fn ($s) => !$this->isWalkIn($s->customer_name));
        $walkInSales = $sales->filter(fn ($s) => $this->isWalkIn($s->customer_name));

        $walkInCount = $walkInSales->count();

        $customerGroups = $nonWalkInSales->groupBy(fn ($s) => strtolower(trim($s->customer_name)));
        $totalCustomers = $customerGroups->count();

        $now = now();
        $customerFirstSale = [];
        foreach ($nonWalkInSales as $sale) {
            $key = strtolower(trim($sale->customer_name));
            if (!isset($customerFirstSale[$key]) || $sale->created_at->lt($customerFirstSale[$key])) {
                $customerFirstSale[$key] = $sale->created_at;
            }
        }

        $newThisMonth = 0;
        $returningCount = 0;
        foreach ($customerFirstSale as $firstDate) {
            if ($firstDate->isCurrentMonth()) {
                $newThisMonth++;
            } else {
                $returningCount++;
            }
        }

        $newVsReturningPct = $totalCustomers > 0 ? 0 : 0;
        $newPct = $totalCustomers > 0 ? round($newThisMonth / $totalCustomers * 100) : 0;
        $returningPct = $totalCustomers > 0 ? round($returningCount / $totalCustomers * 100) : 0;

        $totalRevenue = $nonWalkInSales->sum('grand_total');
        $avgOrderValue = $totalCustomers > 0 ? round($totalRevenue / $totalCustomers) : 0;

        $customerSummary = $customerGroups->map(function ($group, $key) {
            $totalOrders = $group->count();
            $totalQuantity = $group->sum('quantity_sold');
            $totalSpending = $group->sum('grand_total');
            $avgOrder = $totalOrders > 0 ? round($totalSpending / $totalOrders) : 0;
            $lastPurchase = $group->max('created_at');

            $phone = $group->firstWhere('customer_phone', '!=', null)?->customer_phone
                ?? $group->pluck('customer_phone')->filter()->first()
                ?? '';

            $location = $group->firstWhere('customer_location', '!=', null)?->customer_location
                ?? $group->pluck('customer_location')->filter()->first()
                ?? '';

            $allItems = $group->flatMap(fn ($s) => $s->items ?? []);
            $topProducts = collect($allItems)
                ->groupBy('productName')
                ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->sum('quantity')])
                ->sortByDesc('count')
                ->take(3)
                ->values()
                ->toArray();

            return [
                'name' => $group->first()->customer_name,
                'phone' => $phone,
                'location' => $location,
                'totalOrders' => $totalOrders,
                'totalQuantity' => $totalQuantity,
                'totalSpending' => $totalSpending,
                'avgOrderValue' => $avgOrder,
                'lastPurchaseDate' => $lastPurchase instanceof \Carbon\Carbon ? $lastPurchase->toDateTimeString() : null,
                'topProducts' => $topProducts,
            ];
        })->sortByDesc('totalSpending')->values()->toArray();

        $topCustomers = collect($customerSummary)->take(10)->toArray();

        $totalSales = $sales->sum('grand_total');
        $salesByLocation = $nonWalkInSales
            ->filter(fn ($s) => !empty($s->customer_location))
            ->groupBy('customer_location')
            ->map(function ($group) use ($totalSales) {
                $locTotal = $group->sum('grand_total');
                return [
                    'location' => $group->first()->customer_location,
                    'totalSales' => $locTotal,
                    'percentage' => $totalSales > 0 ? round($locTotal / $totalSales * 100) : 0,
                ];
            })
            ->sortByDesc('totalSales')
            ->values()
            ->toArray();

        $monthlyTrends = $sales
            ->groupBy(fn ($s) => $s->created_at->format('Y-m'))
            ->map(function ($group, $month) {
                $uniqueCustomers = $group
                    ->filter(fn ($s) => !$this->isWalkIn($s->customer_name))
                    ->pluck('customer_name')
                    ->map(fn ($n) => strtolower(trim($n)))
                    ->unique()
                    ->count();
                return [
                    'month' => \Carbon\Carbon::parse($month . '-01')->format('M'),
                    'totalSpending' => $group->sum('grand_total'),
                    'orderCount' => $group->count(),
                    'uniqueCustomers' => $uniqueCustomers,
                ];
            })
            ->sortBy(function ($item, $key) { return $key; })
            ->take(12)
            ->values()
            ->toArray();

        $dayCounts = $nonWalkInSales->groupBy(fn ($s) => $s->created_at->format('l'))
            ->map(fn ($group) => $group->count());
        $mostFrequentDay = $dayCounts->isNotEmpty() ? $dayCounts->sortDesc()->keys()->first() : '';

        $hourCounts = $nonWalkInSales->groupBy(fn ($s) => $s->created_at->format('H:00'))
            ->map(fn ($group) => $group->count());
        $mostFrequentHour = $hourCounts->isNotEmpty() ? $hourCounts->sortDesc()->keys()->first() : '';

        $avgSpendingPerCustomer = $totalCustomers > 0 ? round($totalRevenue / $totalCustomers) : 0;

        return [
            'overview' => [
                'totalCustomers' => $totalCustomers,
                'newThisMonth' => $newThisMonth,
                'returningCustomers' => $returningCount,
                'walkInCount' => $walkInCount,
            ],
            'customerSummary' => $customerSummary,
            'topCustomers' => $topCustomers,
            'salesByLocation' => $salesByLocation,
            'newVsReturning' => [
                'newCount' => $newThisMonth,
                'returningCount' => $returningCount,
                'newPct' => $newPct,
                'returningPct' => $returningPct,
            ],
            'monthlyTrends' => $monthlyTrends,
            'purchaseBehavior' => [
                'avgSpendingPerCustomer' => $avgSpendingPerCustomer,
                'mostFrequentDay' => $mostFrequentDay,
                'mostFrequentHour' => $mostFrequentHour,
            ],
        ];
    }

    public function searchFromSales(int $shopId, string $query): array
    {
        $salesCustomers = Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->when($query, fn ($q) => $q->where(function ($q) use ($query) {
                $q->where('customer_name', 'like', "%{$query}%")
                  ->orWhere('customer_phone', 'like', "%{$query}%");
            }))
            ->select('customer_name', 'customer_phone')
            ->groupBy('customer_name', 'customer_phone')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'name' => $s->customer_name,
                'phone' => $s->customer_phone ?? '',
            ])
            ->toArray();

        $directoryCustomers = $this->model->where('shop_id', $shopId)
            ->when($query, fn ($q) => $q->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            }))
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'phone' => $c->phone ?? '',
            ])
            ->toArray();

        $merged = collect(array_merge($salesCustomers, $directoryCustomers))
            ->unique('name')
            ->values()
            ->toArray();

        return $merged;
    }
}
