@php
    $signature = config('company.email_signature');
    $logoPath = public_path('images/app-logo.png');
    $logoSrc = file_exists($logoPath)
        ? (isset($message) ? $message->embed($logoPath) : asset('images/app-logo.png'))
        : null;
    $signatureImagePath = filled($signature['image_path'] ?? null) ? public_path($signature['image_path']) : null;
    $signatureImageSrc = $signatureImagePath && file_exists($signatureImagePath)
        ? (isset($message) ? $message->embed($signatureImagePath) : asset($signature['image_path']))
        : null;
    $contactLine = collect([
        filled($signature['phone'] ?? null) ? 'Phone: '.$signature['phone'] : null,
        filled($signature['landline'] ?? null) ? 'Landline: '.$signature['landline'] : null,
    ])->filter()->implode(' | ');
@endphp

@if($signatureImageSrc)
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:28px; border-top:1px solid #d7e2e8; padding-top:16px;">
        <tr>
            <td>
                <img src="{{ $signatureImageSrc }}" width="680" alt="Company email signature" style="display:block; width:100%; max-width:680px; height:auto; border:0;">
            </td>
        </tr>
    </table>
@else
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:28px; border-top:1px solid #d7e2e8; padding-top:16px;">
        <tr>
            @if($logoSrc)
                <td width="96" valign="top" style="padding-right:14px;">
                    <img src="{{ $logoSrc }}" width="88" alt="Your Company" style="display:block; width:88px; height:auto; border:0;">
                </td>
            @endif
            <td valign="top" style="font-size:12px; line-height:17px; color:#111827;">
                <div style="font-size:15px; line-height:20px; color:#005f91; font-weight:700; text-transform:uppercase;">{{ $signature['company'] }}</div>
                @if($contactLine)
                    <div><strong>{{ $contactLine }}</strong></div>
                @endif
                @if(filled($signature['email'] ?? null) || filled($signature['website'] ?? null))
                    <div>
                        @if(filled($signature['email'] ?? null))
                            Email: <span style="color:#005f91;">{{ $signature['email'] }}</span>
                        @endif
                        @if(filled($signature['email'] ?? null) && filled($signature['website'] ?? null))
                            |
                        @endif
                        @if(filled($signature['website'] ?? null))
                            <span style="color:#005f91;">{{ $signature['website'] }}</span>
                        @endif
                    </div>
                @endif
                @if(filled($signature['address'] ?? null))
                    <div>{{ $signature['address'] }}</div>
                @endif
            </td>
        </tr>
    </table>
@endif
