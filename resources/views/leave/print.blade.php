<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Application &mdash; {{ $leave->user->name }}</title>
    <style>
        html, body { margin: 0; padding: 0; background: #f1f2f4; }
        body { font-family: Helvetica, Arial, sans-serif; }
        .toolbar {
            max-width: 210mm; margin: 0 auto; padding: 10mm 12mm 0;
            display: flex; gap: 8px; align-items: center;
        }
        .toolbar button, .toolbar a {
            font: inherit; font-size: 9pt; text-decoration: none; cursor: pointer;
            border: 1px solid #bbb; background: #fff; color: #111;
            padding: 7px 14px; border-radius: 4px;
        }
        .toolbar button { background: #087aa5; border-color: #087aa5; color: #fff; }
        .toolbar .hint { margin-left: auto; font-size: 8pt; color: #666; }
        .sheet {
            background: #fff; max-width: 210mm; margin: 6mm auto 12mm;
            padding: 12mm; box-shadow: 0 1px 10px rgba(0, 0, 0, .16);
        }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; }
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('leave.pdf', $leave) }}">Download PDF</a>
        <a href="{{ route('leave.show', $leave) }}">Back to application</a>
        <span class="hint">Print or save as PDF from the browser dialog.</span>
    </div>

    <div class="sheet">
        @include('leave.partials.datf025', ['leave' => $leave])
    </div>
</body>
</html>
