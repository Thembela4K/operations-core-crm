@extends('emails.layout')

@section('title', "Invoice {$invoice->invoice_number}")
@section('preheader', "Invoice {$invoice->invoice_number} from ".config('company.email_signature.company', 'your company').".")
@section('heading', 'Invoice')

@section('content')
    <p style="font-size:15px; line-height:24px; color:#374151; margin:0 0 18px;">
        Dear {{ $invoice->client->name }},
    </p>
    <p style="font-size:15px; line-height:24px; color:#374151; margin:0 0 18px;">
        Please find below the invoice summary from {{ config('company.email_signature.company', 'your company') }}.
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:18px 0; font-size:14px;">
        <tr><td style="padding:8px 0; color:#6b7280;">Invoice</td><td style="padding:8px 0; color:#111827; font-weight:700;">{{ $invoice->invoice_number }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">Issue Date</td><td style="padding:8px 0; color:#111827;">{{ $invoice->issue_date->toFormattedDateString() }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">Due Date</td><td style="padding:8px 0; color:#111827;">{{ $invoice->due_date->toFormattedDateString() }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">VAT</td><td style="padding:8px 0; color:#111827;">E{{ number_format((float) $invoice->vat_total, 2) }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">Total</td><td style="padding:8px 0; color:#111827;">E{{ number_format((float) $invoice->total, 2) }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">Balance Due</td><td style="padding:8px 0; color:#111827; font-weight:700;">E{{ number_format((float) $invoice->balance_due, 2) }}</td></tr>
    </table>
    @if($invoice->terms)
        <p style="font-size:14px; line-height:22px; color:#374151; margin:18px 0 0;"><strong>Terms:</strong><br>{!! nl2br(e($invoice->terms)) !!}</p>
    @endif
@endsection
