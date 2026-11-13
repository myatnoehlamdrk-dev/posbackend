<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperationFeeRequest;
use App\Http\Requests\UpdateOperationFeeRequest;
use App\Models\OperationFee;
use App\Services\OperationFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationFeeController extends Controller
{
    public function __construct(
        private readonly OperationFeeService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $shopId = $request->user()->shop_id;
        $year = $request->integer('year', (int) date('Y'));
        $month = $request->integer('month', (int) date('n'));
        $fees = $this->service->listForShop($shopId, $year, $month);
        $settings = $this->service->getSettings($shopId);

        return response()->json([
            'fees' => $fees,
            'settings' => [
                'allocationMethod' => $settings->allocation_method,
            ],
        ]);
    }

    public function store(StoreOperationFeeRequest $request): JsonResponse
    {
        $year = $request->integer('year', (int) date('Y'));
        $month = $request->integer('month', (int) date('n'));
        $fee = $this->service->create(
            $request->user()->shop_id,
            $year,
            $month,
            $request->validated()
        );

        return response()->json($fee, 201);
    }

    public function update(UpdateOperationFeeRequest $request, OperationFee $fee): JsonResponse
    {
        $fee = $this->service->update($fee, $request->validated());

        return response()->json($fee);
    }

    public function destroy(OperationFee $fee): JsonResponse
    {
        $this->service->delete($fee);

        return response()->json(null, 204);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'allocationMethod' => ['required', 'string', 'in:revenue,unitsSold,grossProfit'],
        ]);

        $setting = $this->service->updateSettings(
            $request->user()->shop_id,
            $data['allocationMethod']
        );

        return response()->json([
            'allocationMethod' => $setting->allocation_method,
        ]);
    }
}
