<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->saleService->list($request);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        return $this->saleService->create($request->validated());
    }

    public function show(Sale $sale): JsonResponse
    {
        return $this->saleService->show($sale);
    }

    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        return $this->saleService->update($request->validated(), $sale);
    }

    public function destroy(Sale $sale): JsonResponse
    {
        return $this->saleService->delete($sale);
    }

    public function destroyItem(Sale $sale, SaleItem $saleItem): JsonResponse
    {
        return $this->saleService->deleteItem($sale, $saleItem);
    }
}
