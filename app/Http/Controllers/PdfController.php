<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Shop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function generateVoucher(Sale $sale)
    {
        $sale->load('saleItems');

        $shop = Shop::first();
        $shopName = $shop->shop_name ?? '';
        $shopAddress = $shop->shop_physical_address ?? '';
        $shopPhone = $shop->owner_phone ?? '';
        $shopEmail = $shop->owner_email ?? '';
        $shopImage = $shop->shop_image ?? '';

        $items = $sale->saleItems;
        $subtotal = $items->sum('subtotal');
        $discountPct = $sale->discount;
        $discountAmt = $subtotal > 0 ? ($subtotal * $discountPct / 100) : 0;

        $pdf = Pdf::loadView('sales.voucher', [
            'sale' => $sale,
            'shopName' => $shopName,
            'shopAddress' => $shopAddress,
            'shopPhone' => $shopPhone,
            'shopEmail' => $shopEmail,
            'shopImage' => $shopImage,
            'subtotal' => $subtotal,
            'discountPct' => $discountPct,
            'discountAmt' => $discountAmt,
        ]);

        return $pdf->download('voucher_'.$sale->voucher_no.'.pdf');
    }
}
