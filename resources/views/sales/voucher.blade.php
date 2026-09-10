<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; font-size: 13px; color: #111827; }
        .page { width: 100%; }

        .header {
            background: linear-gradient(135deg, #7C3AED, #5B21B6);
            color: white;
            text-align: center;
            padding: 24px 20px;
        }
        .header .shop-name { font-size: 20px; font-weight: 700; }
        .header .shop-info { font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 3px; }
        .header .invoice-title {
            font-size: 22px; font-weight: 700; letter-spacing: 3px;
            margin-top: 12px;
        }
        .header .voucher-no { font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 4px; }

        .divider { border: none; border-top: 1px solid #E5E7EB; margin: 0 20px; }

        .section { padding: 16px 20px; }
        .info-row {
            display: flex; justify-content: space-between;
            padding: 5px 0;
        }
        .info-label { color: #6B7280; font-size: 13px; }
        .info-value { font-weight: 600; font-size: 13px; }

        .customer-box {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 20px;
        }
        .customer-icon {
            width: 36px; height: 36px; background: #F5F0FF;
            border-radius: 8px; text-align: center; line-height: 36px;
            font-size: 16px;
        }
        .customer-label { font-size: 11px; color: #6B7280; }
        .customer-name { font-size: 14px; font-weight: 600; }
        .customer-phone { font-size: 12px; color: #6B7280; }

        .items-header, .items-row {
            display: flex; padding: 8px 20px;
        }
        .items-header {
            font-size: 11px; font-weight: 600; color: #6B7280;
        }
        .items-row {
            border-bottom: 1px solid #F3F4F6;
            padding: 10px 20px;
        }
        .items-row:last-child { border-bottom: none; }
        .col-item { flex: 4; }
        .col-qty { flex: 1; text-align: center; }
        .col-price { flex: 2; text-align: right; }
        .col-total { flex: 2; text-align: right; font-weight: 600; }
        .item-name { font-weight: 600; font-size: 13px; }
        .item-variant { font-size: 11px; color: #6B7280; margin-top: 2px; }

        .summary { padding: 16px 20px; }
        .summary-row {
            display: flex; justify-content: space-between;
            padding: 4px 0; font-size: 13px;
        }
        .summary-label { color: #6B7280; }
        .summary-value { font-weight: 600; }
        .summary-discount { color: #EF4444; font-weight: 600; }
        .summary-total {
            font-size: 16px; font-weight: 700;
            border-top: 1px dashed #E5E7EB;
            padding-top: 12px; margin-top: 8px;
        }
        .summary-total .total-amount { color: #7C3AED; font-size: 18px; }
        .payment-box {
            background: #F5F0FF; border-radius: 8px;
            padding: 10px 14px; margin-top: 12px;
            display: flex; justify-content: space-between;
        }
        .payment-label { color: #6B7280; font-size: 13px; }
        .payment-value { font-weight: 600; color: #7C3AED; font-size: 13px; }

        .notes-box {
            margin: 0 20px 16px 20px; padding: 12px;
            background: #FEF9C3; border-radius: 8px;
        }
        .notes-title { font-size: 11px; font-weight: 600; color: #92400E; }
        .notes-text { font-size: 12px; color: #78350F; margin-top: 4px; }

        .footer {
            background: #F9FAFB; text-align: center;
            padding: 20px; border-radius: 0 0 12px 12px;
        }
        .footer .check { font-size: 24px; color: #16A34A; }
        .footer .thank-text { font-weight: 600; margin-top: 6px; font-size: 13px; }
        .footer .item-count { color: #6B7280; font-size: 12px; margin-top: 4px; }
    </style>
</head>
<body>
<div class="page">
    {{-- Header --}}
    <div class="header">
        @if($shopImage)
            <div style="margin-bottom: 8px;">
                <img src="{{ $shopImage }}" style="width:64px;height:64px;border-radius:12px;object-fit:cover;">
            </div>
        @endif
        @if($shopName)
            <div class="shop-name">{{ $shopName }}</div>
        @endif
        @if($shopAddress)
            <div class="shop-info">{{ $shopAddress }}</div>
        @endif
        @if($shopPhone)
            <div class="shop-info">{{ $shopPhone }}</div>
        @endif
        @if($shopEmail)
            <div class="shop-info">{{ $shopEmail }}</div>
        @endif
        <div class="invoice-title">INVOICE</div>
        <div class="voucher-no">{{ $sale->voucher_no }}</div>
    </div>

    <hr class="divider">

    {{-- Invoice Info --}}
    <div class="section">
        <div class="info-row">
            <span class="info-label">Voucher ID</span>
            <span class="info-value">{{ $sale->voucher_no }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Order ID</span>
            <span class="info-value">{{ $sale->order_id }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Date</span>
            <span class="info-value">{{ $sale->created_at->format('d/m/Y') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Time</span>
            <span class="info-value">{{ $sale->created_at->format('h:i A') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Staff</span>
            <span class="info-value">{{ $sale->user_name }}</span>
        </div>
    </div>

    <hr class="divider">

    {{-- Customer --}}
    <div class="customer-box">
        <div class="customer-icon">👤</div>
        <div>
            <div class="customer-label">Customer</div>
            <div class="customer-name">{{ $sale->customer_name ?? 'Customer' }}</div>
            @if($sale->customer_phone)
                <div class="customer-phone">{{ $sale->customer_phone }}</div>
            @endif
        </div>
    </div>

    <hr class="divider">

    {{-- Items Header --}}
    <div class="items-header">
        <div class="col-item">Item</div>
        <div class="col-qty">Qty</div>
        <div class="col-price">Price</div>
        <div class="col-total">Total</div>
    </div>

    {{-- Items --}}
    @foreach($sale->saleItems as $item)
        <div class="items-row">
            <div class="col-item">
                <div class="item-name">{{ $item->product_name }}</div>
                @php
                    $variant = collect();
                    if ($item->size && $item->size !== 'Regular') $variant->push($item->size);
                    if ($item->color && $item->color !== '') $variant->push($item->color);
                @endphp
                @if($variant->isNotEmpty())
                    <div class="item-variant">{{ $variant->implode(', ') }}</div>
                @endif
            </div>
            <div class="col-qty">{{ $item->quantity }}</div>
            <div class="col-price">{{ number_format($item->unit_price) }}</div>
            <div class="col-total">{{ number_format($item->subtotal) }}</div>
        </div>
    @endforeach

    <hr class="divider">

    {{-- Summary --}}
    <div class="summary">
        <div class="summary-row">
            <span class="summary-label">Subtotal</span>
            <span class="summary-value">{{ number_format($subtotal) }}</span>
        </div>
        @if($discountPct > 0)
            <div class="summary-row">
                <span class="summary-label">Discount ({{ $discountPct }}%)</span>
                <span class="summary-discount">-{{ number_format($discountAmt) }}</span>
            </div>
        @endif
        <div class="summary-row summary-total">
            <span>Total Payable</span>
            <span class="total-amount">{{ number_format($sale->grand_total) }}</span>
        </div>
        <div class="payment-box">
            <span class="payment-label">Payment Method</span>
            <span class="payment-value">{{ $sale->pay_method ?? 'Cash' }}</span>
        </div>
    </div>

    {{-- Notes --}}
    @if($sale->notes)
        <div class="notes-box">
            <div class="notes-title">Notes</div>
            <div class="notes-text">{{ $sale->notes }}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <div class="check">✓</div>
        <div class="thank-text">Thank you for your purchase!</div>
        <div class="item-count">Total Items: {{ $sale->saleItems->count() }}</div>
    </div>
</div>
</body>
</html>
