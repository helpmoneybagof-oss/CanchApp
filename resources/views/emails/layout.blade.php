<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f3f4f6; color: #1f2937; line-height: 1.6; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 32px 40px; text-align: center; }
        .header .logo-circle { width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; color: #ffffff; margin-bottom: 12px; line-height: 56px; }
        .header h1 { color: #ffffff; font-size: 20px; font-weight: 700; margin: 0; }
        .header p { color: rgba(255,255,255,0.85); font-size: 14px; margin-top: 4px; }
        .body { padding: 40px; }
        .greeting { font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 8px; }
        .text { font-size: 15px; color: #4b5563; margin-bottom: 20px; }
        .card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; margin: 24px 0; }
        .card-title { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; }
        .detail-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 14px; color: #6b7280; }
        .detail-value { font-size: 14px; color: #111827; font-weight: 500; text-align: right; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .btn { display: block; width: fit-content; margin: 0 auto; padding: 14px 32px; background: #10b981; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600; text-align: center; }
        .btn:hover { background: #059669; }
        .divider { height: 1px; background: #e5e7eb; margin: 24px 0; }
        .total-row { display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: #111827; padding-top: 12px; border-top: 2px solid #e5e7eb; margin-top: 12px; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 24px 40px; text-align: center; }
        .footer p { font-size: 13px; color: #9ca3af; margin-bottom: 4px; }
        .footer a { color: #10b981; text-decoration: none; }
        .confirmation-code { font-size: 28px; font-weight: 800; color: #059669; letter-spacing: 0.1em; text-align: center; background: #d1fae5; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .alert-box { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .alert-box p { color: #92400e; font-size: 14px; }
        .alert-red { background: #fee2e2; border-color: #fca5a5; }
        .alert-red p { color: #991b1b; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        @php $appName = \App\Models\Setting::getValue('court_name', config('app.name')); @endphp
        <div class="logo-circle">{{ strtoupper(substr($appName, 0, 1)) }}</div>
        <h1>{{ $appName }}</h1>
        <p>{{ \App\Models\Setting::getValue('court_address', '') }}</p>
    </div>
    <div class="body">
        @yield('content')
    </div>
    <div class="footer">
        <p>{{ \App\Models\Setting::getValue('court_name', config('app.name')) }}</p>
        @if(\App\Models\Setting::getValue('contact_phone'))
            <p>📞 {{ \App\Models\Setting::getValue('contact_phone') }}</p>
        @endif
        @if(\App\Models\Setting::getValue('contact_email'))
            <p>✉️ <a href="mailto:{{ \App\Models\Setting::getValue('contact_email') }}">{{ \App\Models\Setting::getValue('contact_email') }}</a></p>
        @endif
        <p style="margin-top:12px;">Este correo fue enviado automáticamente, por favor no respondas a este mensaje.</p>
    </div>
</div>
</body>
</html>
