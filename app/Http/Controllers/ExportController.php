<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    public function sales(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        return $this->exportService->exportSales($startDate, $endDate);
    }

    public function orders(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        return $this->exportService->exportOrders($startDate, $endDate);
    }
}
