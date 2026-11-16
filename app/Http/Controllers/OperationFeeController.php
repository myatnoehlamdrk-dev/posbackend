<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperationFeeRequest;
use App\Http\Requests\UpdateOperationFeeRequest;
use App\Models\OperationFee;
use App\Services\DashboardService;
use App\Services\OperationFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationFeeController extends Controller
{
    public function __construct(
        private readonly OperationFeeService $service,
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $shopId = $request->user()->shop_id;
        $fees = $this->service->listForShop($shopId);
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
        $fee = $this->service->create(
            $request->user()->shop_id,
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

    public function report(Request $request)
    {
        $shopId = $request->user()->shop_id;
        $year = $request->integer('year', (int) date('Y'));
        $month = $request->integer('month', (int) date('n'));

        $sales = collect($this->dashboardService->getMonthlySales($shopId, $year));
        $monthData = $sales->firstWhere('month', $month) ?? [];

        $revenue = (float) ($monthData['total'] ?? 0);
        $orders = (int) ($monthData['transactions'] ?? 0);
        $cogs = (float) ($monthData['cogs'] ?? 0);
        $purchases = (float) ($monthData['purchases'] ?? 0);
        $units = (int) ($monthData['units'] ?? 0);

        $breakdown = $this->service->monthlyBreakdown($shopId, $year, $month, $revenue, $orders);
        $operationFees = (float) $breakdown['total'];
        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $operationFees;
        $shopName = $request->user()->shop?->shop_name ?? 'My shop';
        $filename = 'operation_fee_report_'.$year.'_'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.csv';

        return response()->streamDownload(function () use (
            $shopName, $year, $month, $revenue, $orders, $units, $cogs, $purchases,
            $grossProfit, $operationFees, $netProfit, $breakdown
        ) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel reads the MMK/Unicode text correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $row = fn (array $fields) => fputcsv($out, $fields, ',', '"', '');

            $row(['Operation Fee Report']);
            $row(['Shop', $shopName]);
            $row(['Period', str_pad((string) $month, 2, '0', STR_PAD_LEFT).'/'.$year]);
            $row([]);

            $row(['Monthly summary']);
            $row(['Metric', 'Value']);
            $row(['Revenue (MMK)', number_format($revenue, 2, '.', '')]);
            $row(['Transactions', $orders]);
            $row(['Units sold', $units]);
            $row(['Cost of goods sold (MMK)', number_format($cogs, 2, '.', '')]);
            $row(['Purchase cost / outflow (MMK)', number_format($purchases, 2, '.', '')]);
            $row(['Gross profit (MMK)', number_format($grossProfit, 2, '.', '')]);
            $row(['Operation fees (MMK)', number_format($operationFees, 2, '.', '')]);

            // Each fee's computed amount sits directly under the total, so the
            // operation-fee figure can be traced without a separate table.
            foreach ($breakdown['fees'] as $fee) {
                if (! $fee['is_active']) {
                    continue;
                }

                $row([
                    '   '.$fee['name'].' (MMK)',
                    number_format((float) $fee['monthly_cost'], 2, '.', ''),
                ]);
            }

            $row(['Net profit after operation fees (MMK)', number_format($netProfit, 2, '.', '')]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Real per-product allocation preview for the selected month and method.
     */
    public function productImpact(Request $request): JsonResponse
    {
        $shopId = $request->user()->shop_id;
        $year = $request->integer('year', (int) date('Y'));
        $month = $request->integer('month', (int) date('n'));

        $allowed = ['revenue', 'unitsSold', 'grossProfit'];
        $method = (string) $request->input(
            'allocationMethod',
            $this->service->getSettings($shopId)->allocation_method,
        );

        if (! in_array($method, $allowed, true)) {
            $method = 'revenue';
        }

        $sales = collect($this->dashboardService->getMonthlySales($shopId, $year));
        $monthData = $sales->firstWhere('month', $month) ?? [];

        $productId = $request->integer('productId');

        return response()->json($this->service->productImpact(
            $shopId,
            $year,
            $month,
            $method,
            (float) ($monthData['total'] ?? 0),
            (int) ($monthData['transactions'] ?? 0),
            $productId > 0 ? $productId : null,
        ));
    }

    public function settings(Request $request): JsonResponse
    {
        $settings = $this->service->getSettings($request->user()->shop_id);

        return response()->json([
            'allocationMethod' => $settings->allocation_method,
        ]);
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
