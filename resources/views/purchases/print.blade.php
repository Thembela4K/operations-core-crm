<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $purchase->purchase_number }}</title>
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
        .field .k { display: inline-block; min-width: 84px; font-weight: 700; color: #333; }
        .addr { margin: 2px 0 0 84px; line-height: 1.4; white-space: pre-line; }

        .lines { margin-top: 14px; border: 1px solid #1a1a1a; }
        .lines th {
            background: #efefef; font-size: 10.5px; text-transform: uppercase; letter-spacing: .3px;
            border-bottom: 1px solid #1a1a1a; border-right: 1px solid #9a9a9a; padding: 6px 8px; text-align: left;
        }
        .lines th:last-child, .lines td:last-child { border-right: 0; }
        .lines td { border-bottom: 1px solid #dcdcdc; border-right: 1px solid #dcdcdc; padding: 6px 8px; vertical-align: top; }
        .lines tbody tr:last-child td { border-bottom: 0; }
        .num { text-align: right; white-space: nowrap; }
        .col-qty { width: 58px; }
        .col-rate { width: 88px; }
        .col-amount { width: 100px; }
        .desc { white-space: pre-line; }

        .footer { margin-top: 14px; }
        .foot-label { font-weight: 700; font-size: 10.5px; text-transform: uppercase; letter-spacing: .3px; color: #333; margin-bottom: 3px; }
        .foot-text { line-height: 1.45; white-space: pre-line; margin-bottom: 10px; }

        .totals { border: 1px solid #1a1a1a; }
        .totals td { padding: 6px 10px; font-size: 11.5px; border-bottom: 1px solid #dcdcdc; }
        .totals tr:last-child td { border-bottom: 0; background: #efefef; font-size: 13px; font-weight: 700; }
        .totals .lbl { text-align: right; font-weight: 600; width: 58%; }
        .totals .amt { text-align: right; white-space: nowrap; width: 42%; }

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
                <div class="doc-title">Purchase Order</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="col left-col">
                <div class="field"><span class="k">Vendor</span>{{ optional($purchase->supplier)->name ?? '—' }}</div>
                <div class="field">
                    <span class="k">Vendor Addr</span>
                    <span class="addr">{{ optional($purchase->supplier)->address ?: '—' }}</span>
                </div>
                <div class="field">
                    <span class="k">Ship To</span>
                    <span class="addr">{{ $purchase->ship_to ?: (optional($purchase->department)->name ?: '—') }}</span>
                </div>
            </td>
            <td class="col">
                <div class="field"><span class="k">Date</span>{{ optional($purchase->purchase_date)->toFormattedDateString() }}</div>
                <div class="field"><span class="k">P.O. Number</span><strong>{{ $purchase->purchase_number }}</strong></div>
                <div class="field"><span class="k">Terms</span>{{ $purchase->terms ?: '—' }}</div>
                <div class="field"><span class="k">Expected</span>{{ optional($purchase->expected_date)->toFormattedDateString() ?: '—' }}</div>
                <div class="field"><span class="k">Status</span>{{ $purchase->status }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th class="num col-qty">Qty</th>
                <th class="num col-rate">Rate</th>
                <th class="num col-amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($purchase->items as $item)
                <tr>
                    <td>{{ optional($item->catalogItem)->name ?? '—' }}</td>
                    <td class="desc">{{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">E{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">E{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center; color:#666;">No line items recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td style="width:58%; padding-right:18px;">
                @if($purchase->title)
                    <div class="foot-label">Order</div>
                    <div class="foot-text">{{ $purchase->title }}</div>
                @endif
                @if($purchase->notes)
                    <div class="foot-label">Memo</div>
                    <div class="foot-text">{{ $purchase->notes }}</div>
                @endif
                @if($purchase->requisition)
                    <div class="foot-text" style="font-size:10.5px; color:#555;">
                        Requisition: {{ $purchase->requisition->requisition_number }}
                    </div>
                @endif
            </td>
            <td style="width:42%;">
                <table class="totals">
                    <tr><td class="lbl">Order Total</td><td class="amt">E{{ number_format((float) $purchase->amount, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
