<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $requisition->requisition_number }} Requisition</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 28px; }
        .top { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; border-bottom: 3px solid #087aa5; padding-bottom: 18px; }
        .brand { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #087aa5; font-weight: 700; }
        h1 { margin: 10px 0 0; font-size: 28px; letter-spacing: 1px; }
        .meta { margin-top: 18px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; font-size: 13px; }
        .meta div { border: 1px solid #d1d5db; padding: 10px; }
        .label { display: block; font-size: 10px; letter-spacing: 1px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 22px; font-size: 12px; }
        th { background: #f3f4f6; color: #374151; text-align: left; text-transform: uppercase; letter-spacing: .7px; font-size: 10px; }
        th, td { border: 1px solid #9ca3af; padding: 9px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 18px; margin-left: auto; width: 320px; border: 1px solid #9ca3af; }
        .totals div { display: flex; justify-content: space-between; border-bottom: 1px solid #d1d5db; padding: 9px 12px; font-size: 13px; }
        .totals div:last-child { border-bottom: 0; font-weight: 700; background: #f3f4f6; }
        .signatures { margin-top: 44px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; font-size: 13px; }
        .sig { border-top: 1px solid #111827; padding-top: 8px; min-height: 44px; }
        .notes { margin-top: 22px; font-size: 13px; line-height: 1.55; }
        @media print {
            body { margin: 14mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="margin-bottom:16px;">Print</button>

    <section class="top">
        <div>
            <div class="brand">{{ config('company.email_signature.company', 'Your Company') }}</div>
            <h1>REQUISITION</h1>
        </div>
        <div style="text-align:right;">
            <div><strong>To:</strong> {{ $requisition->addressed_to }}</div>
            <div><strong>Ref:</strong> {{ $requisition->requisition_number }}</div>
            <div><strong>Date:</strong> {{ $requisition->created_at?->toFormattedDateString() }}</div>
        </div>
    </section>

    <section class="meta">
        <div><span class="label">Department</span>{{ $requisition->department?->name ?? 'Company-wide' }}</div>
        <div><span class="label">Prepared By</span>{{ $requisition->requester?->name ?? 'Unknown' }}</div>
        <div><span class="label">Status</span>{{ $requisition->status }}</div>
        <div><span class="label">Category</span>{{ $requisition->category }}</div>
        <div><span class="label">Priority</span>{{ $requisition->priority }}</div>
        <div><span class="label">Needed By</span>{{ $requisition->needed_by?->toFormattedDateString() ?? 'Not set' }}</div>
    </section>

    @if(filled($requisition->purpose))
        <div class="notes"><strong>Purpose:</strong> {{ $requisition->purpose }}</div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width:42px;">Item</th>
                <th>Details</th>
                <th style="width:72px;">Type</th>
                <th style="width:64px;">Qty</th>
                <th style="width:92px;">Unit Price</th>
                <th style="width:92px;">T/Price</th>
                <th style="width:230px;">Source</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requisition->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->payment_type }}</td>
                    <td class="num">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="num">E{{ number_format((float) $item->estimated_unit_cost, 2) }}</td>
                    <td class="num">E{{ number_format((float) $item->estimated_total, 2) }}</td>
                    <td>{{ $item->source ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="totals">
        <div><span>Total Bank</span><strong>E{{ number_format((float) $requisition->bank_total, 2) }}</strong></div>
        <div><span>Total Cash</span><strong>E{{ number_format((float) $requisition->cash_total, 2) }}</strong></div>
        <div><span>Total Other</span><strong>E{{ number_format((float) $requisition->other_total, 2) }}</strong></div>
        <div><span>Grand Total</span><strong>E{{ number_format((float) $requisition->estimated_total, 2) }}</strong></div>
    </section>

    <section class="signatures">
        <div class="sig">Requisition prepared by: {{ $requisition->requester?->name ?? '' }}</div>
        <div class="sig">Approval by: {{ $requisition->approver?->name ?? '' }}</div>
        <div class="sig">Funds released by: {{ $requisition->releaser?->name ?? '' }}</div>
    </section>
</body>
</html>
