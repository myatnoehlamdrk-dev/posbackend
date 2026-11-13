<?php

namespace App\Services;

use App\Models\OperationFee;
use App\Models\OperationFeeSetting;
use Illuminate\Support\Facades\DB;

class OperationFeeService
{
    public function listForShop(int $shopId, int $year, int $month): array
    {
        return OperationFee::where('shop_id', $shopId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    public function create(int $shopId, int $year, int $month, array $data): OperationFee
    {
        return OperationFee::create([
            'shop_id' => $shopId,
            'year' => $year,
            'month' => $month,
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
