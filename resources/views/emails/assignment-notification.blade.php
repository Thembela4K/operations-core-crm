@extends('emails.layout')

@section('title', 'Assignment Notification')
@section('preheader', "{$recordType} {$reference} has been assigned to {$assignment->department->name}.")
@section('heading', 'New Assignment')

@section('content')
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px; border:1px solid #d7e2e8; background:#f8fafc;">
        <tr>
            <td style="padding:18px 20px;">
                <div style="font-size:11px; line-height:16px; letter-spacing:1.5px; text-transform:uppercase; color:#0b7faa; font-weight:700;">Department Assignment Notice</div>
                <div style="margin-top:6px; font-size:20px; line-height:27px; color:#111827; font-weight:700;">{{ $reference }} - {{ $title }}</div>
                <div style="margin-top:8px; font-size:14px; line-height:22px; color:#4b5563;">Assigned to {{ $assignment->department->name }} for action in the portal.</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:18px 0; font-size:14px;">
        <tr><td style="padding:9px 0; color:#6b7280; width:34%;">Reference</td><td style="padding:9px 0; color:#111827; font-weight:700;">{{ $reference }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Record Type</td><td style="padding:9px 0; color:#111827;">{{ $recordType }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Assigned to</td><td style="padding:9px 0; color:#111827;">{{ $assignment->assignee_name }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Status</td><td style="padding:9px 0; color:#111827;">{{ $status }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Priority</td><td style="padding:9px 0; color:#111827;">{{ $priority }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">{{ $dueLabel }}</td><td style="padding:9px 0; color:#111827; font-weight:700;">{{ $dueDate }}</td></tr>
        <tr><td style="padding:9px 0; color:#6b7280;">Documents</td><td style="padding:9px 0; color:#111827;">{{ $documentCount }} available in the portal</td></tr>
    </table>

    @if($assignment->instructions)
        <div style="margin:18px 0; padding:14px 16px; background:#f8fafc; border-left:4px solid #0b7faa; color:#374151; font-size:14px; line-height:22px;">
            <strong style="display:block; color:#111827; margin-bottom:4px;">Instructions</strong>
            {{ $assignment->instructions }}
        </div>
    @endif

    @if($importantDates)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0; border:1px solid #d9e2e8; font-size:14px;">
            <tr>
                <td style="padding:10px 12px; background:#f8fafc; color:#111827; font-weight:700;">Important dates</td>
            </tr>
            @foreach($importantDates as $date)
                <tr>
                    <td style="padding:9px 12px; border-top:1px solid #e5edf2; color:#374151;">{{ $date }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($documents)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0; border:1px solid #d9e2e8; font-size:14px;">
            <tr>
                <td colspan="2" style="padding:10px 12px; background:#f8fafc; color:#111827; font-weight:700;">Attached documents</td>
            </tr>
            @foreach($documents as $document)
                <tr>
                    <td style="padding:10px 12px; border-top:1px solid #e5edf2; color:#374151;">
                        <strong style="color:#111827;">{{ $document['name'] }}</strong>
                        <div style="font-size:12px; color:#6b7280;">{{ $document['category'] }}</div>
                    </td>
                    <td align="right" style="padding:10px 12px; border-top:1px solid #e5edf2;">
                        <a href="{{ $document['download_url'] }}" style="display:inline-block; padding:8px 12px; border:1px solid #0b7faa; color:#0b7faa; font-size:13px; font-weight:700; text-decoration:none;">Download</a>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:20px;">
        <tr>
            <td bgcolor="#0b7faa" style="border-radius:3px;">
                <a href="{{ $portalUrl }}" style="display:inline-block; padding:12px 18px; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none;">Open in Portal</a>
            </td>
        </tr>
    </table>

    <p style="margin:18px 0 0; font-size:12px; line-height:18px; color:#6b7280;">Open the portal for the live deadline countdown and document access. Document links are protected by portal login.</p>
@endsection
