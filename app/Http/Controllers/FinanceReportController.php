<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function monthly(Request $request)
    {
        $shopId = $request->user()->shop_id;
        $year = $request->integer('year', (int) date('Y'));
        $month = $request->integer('month', (int) date('n'));

        $sales = $this->dashboardService->getMonthlySales($shopId, $year);
        $monthData = collect($sales)->firstWhere('month', $month) ?? ['total' => 0, 'cogs' => 0, 'units' => 0, 'transactions' => 0, 'purchases' => 0];

        $pdf = Pdf::loadView('finance.monthly_report', [
            'year' => $year,
            'month' => $month,
            'revenue' => $monthData['total'] ?? 0,
            'cogs' => $monthData['cogs'] ?? 0,
            'units' => $monthData['units'] ?? 0,
            'transactions' => $monthData['transactions'] ?? 0,
            'purchases' => $monthData['purchases'] ?? 0,
            'grossProfit' => ($monthData['total'] ?? 0) - ($monthData['cogs'] ?? 0),
        ]);

        return $pdf->download("finance_report_{$year}_{$month}.pdf");
    }
}
