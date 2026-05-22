@extends('emails.layout')

@section('title', 'Due Date Reminder')
@section('preheader', $item['subject'])
@section('heading', 'Due Date Reminder')

@section('content')
    <p style="margin:0 0 16px; font-size:15px; line-height:23px; color:#374151;">
        {{ $item['reminder_note'] }} This reminder is sent only because the assigned department has not submitted a response.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:18px 0; font-size:14px;">
        <tr><td style="padding:9px 0; color:#6b7280; width:34%;">Reference</td><td style="padding:9px 0; color:#111827; font-weight:700;">{{ $item['reference'] }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Title</td><td style="padding:9px 0; color:#111827;">{{ $item['title'] }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Department</td><td style="padding:9px 0; color:#111827;">{{ $item['department'] }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Assigned to</td><td style="padding:9px 0; color:#111827;">{{ $item['owner'] }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Status</td><td style="padding:9px 0; color:#111827;">{{ $item['status'] }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Priority</td><td style="padding:9px 0; color:#111827;">{{ $item['priority'] }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">{{ $item['due_label'] }}</td><td style="padding:9px 0; color:#111827; font-weight:700;">{{ $item['due_on']->toDateString() }}</td></tr>
    </table>

    @if(! empty($item['portal_url']))
        <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:20px;">
            <tr>
                <td bgcolor="#0b7faa" style="border-radius:3px;">
                    <a href="{{ $item['portal_url'] }}" style="display:inline-block; padding:12px 18px; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none;">Open in Portal</a>
                </td>
            </tr>
        </table>
    @endif
@endsection
