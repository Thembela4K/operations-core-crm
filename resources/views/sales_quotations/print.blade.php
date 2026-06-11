<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $salesQuotation->quotation_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
        .top { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #087aa5; padding-bottom: 18px; }
        .logo { max-width: 170px; max-height: 80px; object-fit: contain; }
        h1 { margin: 0; font-size: 26px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border-bottom: 1px solid #d4d4d4; padding: 9px; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; font-size: 12px; text-transform: uppercase; }
        .right { text-align: right; }
        .totals { margin-left: auto; width: 320px; margin-top: 24px; }
        .totals div { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd; }
        .total { font-weight: 700; font-size: 18px; }
        @media print { button { display: none; } body { margin: 18mm; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Print / Save PDF</button>
    <div class="top">
        <div>
            @if(file_exists(public_path('images/app-logo.png')))
                <img class="logo" src="{{ asset('images/app-logo.png') }}" alt="Company logo">
            @endif
            <h2>{{ config('company.email_signature.company', 'Your Company') }}</h2>
        </div>
        <div class="right">
            <h1>Sales Quotation</h1>
            <p><strong>{{ $salesQuotation->quotation_number }}</strong></p>
            <p>Issue Date: {{ $salesQuotation->issue_date->toFormattedDateString() }}<br>Valid Until: {{ $salesQuotation->valid_until->toFormattedDateString() }}</p>
        </div>
    </div>
    <p><strong>Client:</strong> {{ $salesQuotation->client->name }}<br>{!! nl2br(e($salesQuotation->client->address)) !!}</p>
    <h2>{{ $salesQuotation->title }}</h2>
    <table>
        <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">VAT</th><th class="right">Total</th></tr></thead>
        <tbody>
            @foreach($salesQuotation->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="right">E{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">E{{ number_format((float) $item->vat_amount, 2) }}</td>
                    <td class="right">E{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="totals">
        <div><span>Subtotal</span><strong>E{{ number_format((float) $salesQuotation->subtotal, 2) }}</strong></div>
        <div><span>VAT {{ $vatRate }}%</span><strong>E{{ number_format((float) $salesQuotation->vat_total, 2) }}</strong></div>
        <div class="total"><span>Total</span><strong>E{{ number_format((float) $salesQuotation->total, 2) }}</strong></div>
    </div>
    @if($salesQuotation->terms)<p><strong>Terms:</strong><br>{!! nl2br(e($salesQuotation->terms)) !!}</p>@endif
    @if($salesQuotation->notes)<p><strong>Notes:</strong><br>{!! nl2br(e($salesQuotation->notes)) !!}</p>@endif
</body>
</html>
