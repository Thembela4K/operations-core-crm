<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; margin: 26px; font-size: 12px; }
        .toolbar { margin-bottom: 14px; }
        .toolbar button { padding: 7px 14px; font-size: 12px; cursor: pointer; }
        .company { font-size: 19px; font-weight: 700; letter-spacing: .4px; }
        .company-meta { font-size: 10.5px; color: #444; line-height: 1.45; margin-top: 4px; }
        .doc-title { font-size: 20px; font-weight: 700; text-align: right; letter-spacing: 1px; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; }
        .head { border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; }
        .meta { border: 1px solid #1a1a1a; border-top: 0; }
        .meta td { vertical-align: top; padding: 7px 9px; font-size: 11.5px; }
        .meta .col { width: 50%; }
        .meta .left-col { border-right: 1px solid #1a1a1a; }
        .field { margin-bottom: 5px; }
        .field:last-child { margin-bottom: 0; }
        .field .k { display: inline-block; min-width: 92px; font-weight: 700; color: #333; }
        .addr { margin: 2px 0 0 92px; line-height: 1.4; white-space: pre-line; }
        .section { margin-top: 14px; }
        .section h3 { margin: 0 0 4px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .3px; color: #333; }
        .section p { margin: 0; line-height: 1.45; white-space: pre-line; }
        .totals { border: 1px solid #1a1a1a; margin-top: 14px; }
        .totals td { padding: 6px 10px; font-size: 12px; }
        .totals .lbl { text-align: right; font-weight: 600; width: 72%; }
        .totals .amt { text-align: right; white-space: nowrap; width: 28%; font-weight: 700; }
        @media print { .toolbar { display: none; } body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Print / Save PDF</button></div>

    <table class="head">
        <tr>
            <td>
                @if(file_exists(public_path('images/app-logo.png')))
                    <img src="{{ asset('images/app-logo.png') }}" alt="" style="max-height:56px; max-width:180px; object-fit:contain; margin-bottom:6px; display:block;">
                @endif
                <div class="company">{{ config('company.email_signature.company', 'Your Company') }}</div>
                <div class="company-meta">
                    @if(config('company.email_signature.address')){{ config('company.email_signature.address') }}<br>@endif
                    @if(config('company.email_signature.phone'))Phone: {{ config('company.email_signature.phone') }}<br>@endif
                    @if(config('company.email_signature.email')){{ config('company.email_signature.email') }}<br>@endif
                    @if(config('company.email_signature.website')){{ config('company.email_signature.website') }}@endif
                </div>
            </td>
            <td style="text-align:right; vertical-align:top;">
                <div class="doc-title">Sales Order</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="col left-col">
                <div class="field"><span class="k">Customer</span>{{ $order->client?->name ?? '—' }}</div>
                <div class="field">
                    <span class="k">Address</span>
                    <span class="addr">{{ $order->client?->address ?: '—' }}</span>
                </div>
                <div class="field"><span class="k">Class</span>{{ $order->department?->name ?? '—' }}</div>
            </td>
            <td class="col">
                <div class="field"><span class="k">Order #</span><strong>{{ $order->order_number }}</strong></div>
                <div class="field"><span class="k">Order Date</span>{{ $order->order_date?->toFormattedDateString() ?? '—' }}</div>
                <div class="field"><span class="k">Delivery Date</span>{{ $order->delivery_date?->toFormattedDateString() ?? '—' }}</div>
                <div class="field"><span class="k">Customer PO</span>{{ $order->client_po_reference ?: '—' }}</div>
                <div class="field"><span class="k">Status</span>{{ $order->status }}</div>
                @if($order->quotation)
                    <div class="field"><span class="k">Quotation</span>{{ $order->quotation->quotation_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="section">
        <h3>Agreed Scope</h3>
        <p>{{ $order->scope ?: '—' }}</p>
    </div>

    @if($order->delivery_requirements)
        <div class="section">
            <h3>Delivery Requirements</h3>
            <p>{{ $order->delivery_requirements }}</p>
        </div>
    @endif

    @if($order->notes)
        <div class="section">
            <h3>Notes</h3>
            <p>{{ $order->notes }}</p>
        </div>
    @endif

    <table class="totals">
        <tr><td class="lbl">Order Value</td><td class="amt">E{{ number_format((float) $order->value, 2) }}</td></tr>
    </table>
</body>
</html>
