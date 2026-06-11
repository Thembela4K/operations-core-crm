<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0; padding:0; background:#f4f7f9; color:#111827; font-family:Arial, Helvetica, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f9; padding:28px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:760px; background:#ffffff; border:1px solid #d9e2e8;">
                    <tr>
                        <td style="border-top:4px solid #0b7faa; padding:28px 32px 10px;">
                            <div style="font-size:12px; line-height:18px; letter-spacing:2px; text-transform:uppercase; color:#0b7faa; font-weight:700;">{{ config('company.email_signature.company', 'Your Company') }}</div>
                            <h1 style="margin:8px 0 0; font-size:24px; line-height:31px; color:#111827; font-weight:700;">@yield('heading')</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px 30px;">
                            @yield('content')
                            @include('emails.partials.signature')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
