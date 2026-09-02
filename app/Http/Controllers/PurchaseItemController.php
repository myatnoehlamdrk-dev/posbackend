<?php

namespace App\Http\Controllers;

use App\Http\Resources\PurchaseItemResource;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function index(): JsonResponse
    {
        $query = PurchaseItem::with('supplier');

        if (request('status') === 'pending') {
            $query->where('status', 'pending');
        }

        return response()->json(
            PurchaseItemResource::collection(
                $query->latest()->paginate(20)
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'productName' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unitPrice' => ['required', 'integer', 'min:0'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'size' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'sku' => ['nullable', 'string'],
        ]);

        $totalPrice = $data['quantity'] * $data['unitPrice'];

        $product = Product::where('name', $data['productName'])->first();

        if ($product) {
            $this->stockService->add($product->id, $data['quantity']);

            if (!empty($data['supplierId'])) {
                $product->update([
                    'supplier_id' => $data['supplierId'],
                    'supplier_contact' => Supplier::find($data['supplierId'])?->contact,
                    'supplier_address' => Supplier::find($data['supplierId'])?->address,
                ]);
            }

            if (!empty($data['size'])) {
                $product->update(['size' => $data['size']]);
            }
            if (!empty($data['color'])) {
                $product->update(['color' => $data['color']]);
            }
            if (!empty($data['brand'])) {
                $product->update(['brand' => $data['brand']]);
            }
            if (!empty($data['sku'])) {
                $product->update(['sku' => $data['sku']]);
            }

            $productId = $product->id;
        } else {
            $newProduct = Product::create([
                'name' => $data['productName'],
                'stock' => $data['quantity'],
                'size' => $data['size'] ?? null,
                'color' => $data['color'] ?? null,
                'brand' => $data['brand'] ?? null,
                'sku' => $data['sku'] ?? null,
                'supplier_id' => $data['supplierId'] ?? null,
                'active' => true,
            ]);
            $productId = $newProduct->id;
        }

        $purchaseItem = PurchaseItem::create([
            'user_id' => $request->user()?->id,
            'supplier_id' => $data['supplierId'] ?? null,
            'product_id' => $productId,
            'product_name' => $data['productName'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unitPrice'],
            'total_price' => $totalPrice,
            'date' => $data['date'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(new PurchaseItemResource($purchaseItem->fresh(['supplier'])), 201);
    }

    public function show(PurchaseItem $purchaseItem): JsonResponse
    {
        return response()->json(new PurchaseItemResource($purchaseItem->load(['supplier', 'product'])));
    }

    public function update(Request $request, PurchaseItem $purchaseItem): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,completed'],
        ]);

        $purchaseItem->update($data);

        return response()->json(new PurchaseItemResource($purchaseItem->fresh(['supplier'])));
    }

    public function destroy(PurchaseItem $purchaseItem): JsonResponse
    {
        $purchaseItem->delete();

        return response()->json(['message' => 'Purchase item deleted successfully.']);
    }
}
