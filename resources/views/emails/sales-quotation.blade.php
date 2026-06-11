@extends('emails.layout')

@section('title', "Sales Quotation {$salesQuotation->quotation_number}")
@section('preheader', "Sales quotation {$salesQuotation->quotation_number} from ".config('company.email_signature.company', 'your company').".")
@section('heading', 'Sales Quotation')

@section('content')
    <p style="font-size:15px; line-height:24px; color:#374151; margin:0 0 18px;">
        Dear {{ $salesQuotation->client->name }},
    </p>
    <p style="font-size:15px; line-height:24px; color:#374151; margin:0 0 18px;">
        Please find below the quotation summary prepared by {{ config('company.email_signature.company', 'your company') }}.
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:18px 0; font-size:14px;">
        <tr><td style="padding:8px 0; color:#6b7280;">Quotation</td><td style="padding:8px 0; color:#111827; font-weight:700;">{{ $salesQuotation->quotation_number }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">Title</td><td style="padding:8px 0; color:#111827;">{{ $salesQuotation->title }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">Valid Until</td><td style="padding:8px 0; color:#111827;">{{ $salesQuotation->valid_until->toFormattedDateString() }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">VAT</td><td style="padding:8px 0; color:#111827;">E{{ number_format((float) $salesQuotation->vat_total, 2) }}</td></tr>
        <tr><td style="padding:8px 0; color:#6b7280;">Total</td><td style="padding:8px 0; color:#111827; font-weight:700;">E{{ number_format((float) $salesQuotation->total, 2) }}</td></tr>
    </table>
    @if($salesQuotation->terms)
        <p style="font-size:14px; line-height:22px; color:#374151; margin:18px 0 0;"><strong>Terms:</strong><br>{!! nl2br(e($salesQuotation->terms)) !!}</p>
    @endif
@endsection
