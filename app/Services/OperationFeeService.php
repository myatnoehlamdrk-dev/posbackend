<?php

namespace App\Services;

use App\Models\OperationFee;
use App\Models\OperationFeeSetting;
use Illuminate\Support\Facades\DB;

class OperationFeeService
{
    public function listForShop(int $shopId): array
    {
        return OperationFee::where('shop_id', $shopId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    public function create(int $shopId, array $data): OperationFee
    {
        return OperationFee::create([
            'shop_id' => $shopId,
            'name' => $data['name'],
            'category' => $data['category'] ?? 'other',
            'calculation' => $data['calculation'] ?? 'fixedPerMonth',
            'rate' => $data['rate'] ?? 0,
            'is_active' => $data['isActive'] ?? true,
            'icon' => $data['icon'] ?? 'other',
            'tint' => $data['tint'] ?? 'gray',
        ]);
    }

    public function update(OperationFee $fee, array $data): OperationFee
    {
        $fee->update(array_filter([
            'name' => $data['name'] ?? null,
            'category' => $data['category'] ?? null,
            'calculation' => $data['calculation'] ?? null,
            'rate' => array_key_exists('rate', $data) ? $data['rate'] : null,
            'is_active' => array_key_exists('isActive', $data) ? $data['isActive'] : null,
            'icon' => $data['icon'] ?? null,
            'tint' => $data['tint'] ?? null,
        ], fn ($v) => $v !== null));

        return $fee->fresh();
    }

    public function delete(OperationFee $fee): void
    {
        $fee->delete();
    }

    /**
     * The shop-wide rules evaluated for one month.
     *
     * Rules are not stored per month; their monthly amount is computed from
     * the month's revenue and transaction count instead.
     *
     * @return array{fees: array<int, array<string, mixed>>, total: float}
     */
    public function monthlyBreakdown(
        int $shopId,
        int $year,
        int $month,
        float $revenue,
        int $orders,
    ): array {
        $fees = OperationFee::where('shop_id', $shopId)
            ->orderBy('id')
            ->get()
            ->map(fn (OperationFee $fee) => [
                'name' => $fee->name,
                'category' => $fee->category,
                'calculation' => $fee->calculation,
                'rate' => (float) $fee->rate,
                'is_active' => (bool) $fee->is_active,
                'monthly_cost' => round($this->monthlyCost($fee, $year, $month, $revenue, $orders), 2),
            ])
            ->all();

        return [
            'fees' => $fees,
            'total' => round(array_sum(array_column($fees, 'monthly_cost')), 2),
        ];
    }

    /**
     * Add the computed operation-fee total to each month of a sales series.
     *
     * The rules are loaded once, so building a full year costs one extra
     * query instead of one per month.
     *
     * @param  array<int, array<string, mixed>>  $months
     * @return array<int, array<string, mixed>>
     */
    public function attachMonthlyCosts(int $shopId, int $year, array $months): array
    {
        $fees = OperationFee::where('shop_id', $shopId)
            ->orderBy('id')
            ->get();

        foreach ($months as $index => $month) {
            $total = 0.0;

            foreach ($fees as $fee) {
                $total += $this->monthlyCost(
                    $fee,
                    $year,
                    (int) $month['month'],
                    (float) ($month['total'] ?? 0),
                    (int) ($month['transactions'] ?? 0),
                );
            }

            $months[$index]['operation_fee'] = round($total, 2);
        }

        return $months;
    }

    private function monthlyCost(
        OperationFee $fee,
        int $year,
        int $month,
        float $revenue,
        int $orders,
    ): float {
        if (! $fee->is_active) {
            return 0.0;
        }

        $rate = (float) $fee->rate;

        return match ($fee->calculation) {
            'fixedPerMonth' => $rate,
            'percentageOfRevenue' => $revenue * $rate / 100,
            'perOrder' => $orders * $rate,
            'oneTime' => $fee->created_at !== null
                && (int) $fee->created_at->year === $year
                && (int) $fee->created_at->month === $month
                    ? $rate
                    : 0.0,
            default => 0.0,
        };
    }

    public function getSettings(int $shopId): OperationFeeSetting
    {
        return OperationFeeSetting::firstOrCreate(
            ['shop_id' => $shopId],
            ['allocation_method' => 'revenue']
        );
    }

    public function updateSettings(int $shopId, string $allocationMethod): OperationFeeSetting
    {
        $setting = $this->getSettings($shopId);
        $setting->update(['allocation_method' => $allocationMethod]);

        return $setting->fresh();
    }
}
