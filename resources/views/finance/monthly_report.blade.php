<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Finance Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Monthly Finance Report — {{ $month }}/{{ $year }}</h1>
    <table>
        <tr><th>Metric</th><th>Value (MMK)</th></tr>
        <tr><td>Revenue</td><td>{{ number_format($revenue) }}</td></tr>
        <tr><td>Cost of Goods</td><td>{{ number_format($cogs) }}</td></tr>
        <tr><td>Gross Profit</td><td>{{ number_format($grossProfit) }}</td></tr>
        <tr><td>Units Sold</td><td>{{ number_format($units) }}</td></tr>
        <tr><td>Transactions</td><td>{{ number_format($transactions) }}</td></tr>
        <tr><td>Purchase Cost (Outflow)</td><td>{{ number_format($purchases) }}</td></tr>
    </table>
</body>
</html>
