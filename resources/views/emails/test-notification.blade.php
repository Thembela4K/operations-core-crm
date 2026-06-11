@extends('emails.layout')

@section('title', 'Notification Test')
@section('preheader', 'This confirms the Laravel SMTP configuration can send system notifications.')
@section('heading', 'Notification Email Test')

@section('content')
    <p style="margin:0 0 16px; font-size:15px; line-height:23px; color:#374151;">
        This is a development test from Datamatics Eswatini.
        If you received this message, Laravel is able to send notification emails through the configured SMTP account.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:18px 0; font-size:14px;">
        <tr><td style="padding:9px 0; color:#6b7280; width:34%;">Application</td><td style="padding:9px 0; color:#111827;">Datamatics Eswatini</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Environment</td><td style="padding:9px 0; color:#111827;">{{ app()->environment() }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Sent at</td><td style="padding:9px 0; color:#111827;">{{ now()->toDayDateTimeString() }}</td></tr>
    </table>
@endsection
