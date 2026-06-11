@extends('emails.layout')

@section('title', "Requisition {$requisition->requisition_number}")
@section('preheader', "{$requisition->requisition_number} - {$requisition->title}")
@section('heading', "Requisition {$eventLabel}")

@section('content')
    <p style="margin:0 0 18px; font-size:15px; line-height:24px; color:#374151;">
        {{ $messageText }}
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; border:1px solid #d9e2e8; margin:0 0 20px;">
        <tr>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Reference</td>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; font-size:14px; color:#111827;">{{ $requisition->requisition_number }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Title</td>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; font-size:14px; color:#111827;">{{ $requisition->title }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Department</td>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; font-size:14px; color:#111827;">{{ $requisition->department?->name ?? 'Company-wide' }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Status</td>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; font-size:14px; color:#111827;">{{ $requisition->status }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Priority</td>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; font-size:14px; color:#111827;">{{ $requisition->priority }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Total Bank</td>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; font-size:14px; color:#111827;">E{{ number_format((float) $requisition->bank_total, 2) }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Total Cash</td>
            <td style="padding:12px 14px; border-bottom:1px solid #e5edf2; font-size:14px; color:#111827;">E{{ number_format((float) $requisition->cash_total, 2) }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#607080; font-weight:700;">Grand Total</td>
            <td style="padding:12px 14px; font-size:14px; color:#111827;">E{{ number_format((float) $requisition->estimated_total, 2) }}</td>
        </tr>
    </table>

    @if(filled($requisition->purpose))
        <p style="margin:0 0 20px; font-size:14px; line-height:22px; color:#374151;">
            <strong>Purpose:</strong> {{ $requisition->purpose }}
        </p>
    @endif

    <p style="margin:0 0 24px;">
        <a href="{{ $portalUrl }}" style="display:inline-block; background:#087aa5; color:#ffffff; text-decoration:none; padding:12px 18px; border-radius:6px; font-size:14px; font-weight:700;">
            Open in OperationsCore CRM
        </a>
    </p>
@endsection
