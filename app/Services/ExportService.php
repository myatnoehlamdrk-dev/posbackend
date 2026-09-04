<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ExportService
{
    public function exportSales(?string $startDate = null, ?string $endDate = null): JsonResponse
    {
        $query = Sale::with('saleItems')->latest();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $sales = $query->get();

        $csv = $this->buildCsv(
            ['ID', 'Voucher No', 'Customer', 'Items', 'Total', 'Discount', 'Payment', 'Date'],
            $sales->map(fn ($sale) => [
                $sale->id,
                $sale->voucher_no,
                $sale->customer_name ?? 'N/A',
                $sale->product_name,
                $sale->grand_total,
                $sale->discount,
                $sale->pay_method ?? 'N/A',
                $sale->created_at->format('Y-m-d H:i'),
            ])->toArray()
        );

        return response()->json(['csv' => $csv, 'count' => $sales->count()]);
    }

    public function exportOrders(?string $startDate = null, ?string $endDate = null): JsonResponse
    {
        $query = Order::latest();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $orders = $query->get();

        $csv = $this->buildCsv(
            ['ID', 'Order ID', 'Customer', 'Status', 'Total', 'Discount', 'Date'],
            $orders->map(fn ($order) => [
                $order->id,
                $order->order_id,
                $order->customer_name ?? 'N/A',
                $order->status,
                $order->grand_total,
                $order->discount,
                $order->created_at->format('Y-m-d H:i'),
            ])->toArray()
        );

        return response()->json(['csv' => $csv, 'count' => $orders->count()]);
    }

    private function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
