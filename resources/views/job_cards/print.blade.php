<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>JOB CARD {{ $jobCard->job_card_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; margin: 22px; font-size: 11px; }
        .toolbar { margin-bottom: 12px; }
        .toolbar button { padding: 6px 14px; font-size: 12px; cursor: pointer; }

        h1.doc-title { font-size: 20px; font-weight: 700; letter-spacing: 2px; margin: 0 0 10px; }

        table { border-collapse: collapse; width: 100%; }
        .grid td, .grid th { border: 1px solid #1a1a1a; padding: 5px 6px; vertical-align: top; }
        .grid th { background: #f1f1f1; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; text-align: left; }

        .customer { font-weight: 700; font-size: 11.5px; }
        .bank { font-size: 10px; line-height: 1.45; }
        .bank .k { display: inline-block; min-width: 82px; }
        .address { font-size: 10px; line-height: 1.5; }

        .col-head { font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .muted { color: #555; }

        .notes { font-size: 10px; line-height: 1.45; }
        .totals td { border: 1px solid #1a1a1a; padding: 5px 6px; font-size: 11px; }
        .totals .label { text-align: right; font-weight: 700; }

        .footer { margin-top: 12px; border-top: 1px solid #1a1a1a; padding-top: 6px; font-size: 9px; color: #333; line-height: 1.5; }

        @media print {
            .toolbar { display: none; }
            body { margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
    </div>

    <h1 class="doc-title">JOB CARD</h1>

    {{-- Customer, bank details and address --}}
    <table class="grid">
        <tr>
            <td style="width: 62%;">
                <div class="customer">CUSTOMER: {{ $client?->name ?? '—' }}</div>
                <div class="bank" style="margin-top: 6px;">
                    <strong>BANK DETAILS</strong><br>
                    <span class="k">Name:</span>{{ $bank['name'] ?? '' }}<br>
                    <span class="k">Bank:</span>{{ $bank['bank'] ?? '' }}<br>
                    <span class="k">Branch:</span>{{ $bank['branch'] ?? '' }}<br>
                    <span class="k">Branch Code:</span>{{ $bank['branch_code'] ?? '' }}<br>
                    <span class="k">Account No.:</span>{{ $bank['account_number'] ?? '' }}<br>
                    <span class="k">Type:</span>{{ $bank['account_type'] ?? '' }}<br>
                    <span class="k">SWIFT Code:</span>{{ $bank['swift'] ?? '' }}
                </div>
            </td>
            <td style="width: 38%;">
                <div class="address">
                    <strong>ADDRESS:</strong><br>
                    {{ $client?->address ?: '—' }}
                    @if($client?->city)<br>{{ $client->city }}@endif
                    @if($client?->country)<br>{{ $client->country }}@endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Contact row --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <td style="width: 40%;">CONTACT PERSON: {{ $contact?->name ?: '-' }}</td>
            <td style="width: 30%;">TEL: {{ $client?->phone ?? '' }}</td>
            <td style="width: 30%;">CELL: {{ $contact?->phone ?? '' }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td colspan="2">FAX:</td>
        </tr>
    </table>

    {{-- Diagnosis --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <th style="width: 28%;" class="col-head">DIAGNOSIS</th>
            <th style="width: 8%;" class="center">Item</th>
            <th>Description</th>
            <th style="width: 15%;" class="num">Price</th>
        </tr>
        @php($diagnosis = $jobCard->scope ? preg_split('/\r\n|\r|\n/', $jobCard->scope) : [])
        @for($i = 0; $i < max(4, count($diagnosis)); $i++)
            <tr>
                <td>{{ $i === 0 ? '' : '' }}</td>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $diagnosis[$i] ?? '' }}</td>
                <td class="num"></td>
            </tr>
        @endfor
    </table>

    {{-- Parts used --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <th style="width: 28%;" class="col-head">PARTS USED</th>
            <th style="width: 8%;" class="center">Item</th>
            <th>Description</th>
            <th style="width: 15%;"></th>
        </tr>
        @for($i = 0; $i < 4; $i++)
            <tr>
                <td></td>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $i === 0 ? $partsDefault : '' }}</td>
                <td></td>
            </tr>
        @endfor
    </table>

    {{-- Work done --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <th style="width: 28%;" class="col-head">WORK DONE</th>
            <th style="width: 8%;" class="center">Item</th>
            <th>Component Description</th>
            <th style="width: 8%;" class="center">Qty</th>
            <th style="width: 13%;" class="num">Unit Price</th>
            <th style="width: 13%;" class="num">Total</th>
        </tr>
        @foreach($lines as $index => $line)
            <tr>
                <td></td>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $line['description'] }}</td>
                <td class="center">{{ rtrim(rtrim(number_format($line['quantity'], 2, '.', ''), '0'), '.') }}</td>
                <td class="num">{{ number_format($line['unit_price'], 2) }}</td>
                <td class="num">{{ number_format($line['line_total'], 2) }}</td>
            </tr>
        @endforeach
        @for($i = count($lines); $i < 4; $i++)
            <tr>
                <td></td>
                <td class="center">{{ $i + 1 }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endfor
    </table>

    {{-- Project reference --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <td class="center">
                <strong>PROJECT:</strong> {{ strtoupper($jobCard->title) }}
                @if($quotation)
                    &nbsp;&nbsp;&nbsp;<strong>TENDER:</strong> {{ strtoupper($quotation->title) }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="center">
                <strong>REFERENCE:</strong>
                @if($invoice)
                    TAX INVOICE {{ $invoice->invoice_number }} DATED {{ $invoice->issue_date?->format('d/m/Y') }}
                @else
                    JOB CARD {{ $jobCard->job_card_number }}
                @endif
            </td>
        </tr>
    </table>

    {{-- Notes and totals --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <td style="width: 72%;">
                <div class="notes">
                    <strong>Project notes:</strong>
                    {{ $jobCard->notes ?: ($jobCard->scope ?: 'Job card issued for the above project and invoice reference.') }}
                </div>
            </td>
            <td style="width: 28%; padding: 0;">
                <table class="totals">
                    <tr><td class="label">JOB TOTAL</td><td class="num">E{{ number_format($subtotal, 2) }}</td></tr>
                    <tr><td class="label">VAT</td><td class="num">E{{ number_format($vat, 2) }}</td></tr>
                    <tr><td class="label">TOTAL</td><td class="num">E{{ number_format($total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Signature --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <td style="width: 55%; height: 42px;">SIGNATURE:</td>
            <td><strong>FULL NAME:</strong> {{ $technician ?? '' }}</td>
        </tr>
    </table>

    {{-- Administrative details --}}
    <table class="grid" style="border-top: 0;">
        <tr><td class="col-head">Project / Administrative Details</td></tr>
        <tr>
            <td>
                <strong>PREPARED BY:</strong> {{ $preparedBy ?? '' }}
                &nbsp;&nbsp;&nbsp;&nbsp;<strong>DATE:</strong> {{ now()->format('d/m/Y') }}
                &nbsp;&nbsp;&nbsp;&nbsp;<strong>INVOICE NO.:</strong> {{ $invoice?->invoice_number ?? '' }}
            </td>
        </tr>
    </table>

    {{-- Payment and acknowledgement --}}
    <table class="grid" style="border-top: 0;">
        <tr>
            <td style="width: 50%;">
                CASH &nbsp;&nbsp;&nbsp; CHEQUE# &nbsp;&nbsp;&nbsp; A/C
            </td>
            <td style="width: 50%;">
                <strong>COMPLETION ACKNOWLEDGEMENT</strong><br>
                Received in Acceptable Condition by:<br>
                Name: ........................................ &nbsp; Sign: ...................
            </td>
        </tr>
    </table>

    <div class="footer">{{ $footer }}</div>
</body>
</html>
