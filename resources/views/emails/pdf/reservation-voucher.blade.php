<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Reserva</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; }
        .header { background: #059669; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { font-size: 22px; font-weight: 700; }
        .header p { font-size: 12px; opacity: 0.85; margin-top: 4px; }
        .body { padding: 24px; }
        .section-title { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin: 20px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 7px 0; font-size: 12px; }
        td.label { color: #6b7280; width: 45%; }
        td.value { color: #111827; font-weight: 500; }
        .code-box { background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 6px; padding: 12px; text-align: center; margin: 16px 0; }
        .code-box .code { font-size: 22px; font-weight: 800; color: #065f46; letter-spacing: 0.12em; }
        .code-box .label { font-size: 10px; color: #059669; margin-top: 2px; }
        .total-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-top: 16px; }
        .total-row { display: flex; justify-content: space-between; }
        .total-final { font-size: 15px; font-weight: 700; border-top: 2px solid #e5e7eb; padding-top: 8px; margin-top: 8px; }
        .footer { margin-top: 32px; text-align: center; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; background: #fef3c7; color: #92400e; }
        .badge-green { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
@php
    $appName = \App\Models\Setting::getValue('court_name', config('app.name'));
    $appAddress = \App\Models\Setting::getValue('court_address', '');
    $appPhone = \App\Models\Setting::getValue('contact_phone', '');
@endphp

<div class="header">
    <h1>{{ $appName }}</h1>
    <p>Comprobante de Reserva</p>
    @if($appAddress)<p>{{ $appAddress }}</p>@endif
</div>

<div class="body">
    <div class="code-box">
        <div class="code">{{ $reservation->confirmation_code }}</div>
        <div class="label">Código de confirmación</div>
    </div>

    <div class="section-title">Datos del cliente</div>
    <table>
        <tr>
            <td class="label">Nombre</td>
            <td class="value">{{ $reservation->user->name }}</td>
        </tr>
        <tr>
            <td class="label">Email</td>
            <td class="value">{{ $reservation->user->email }}</td>
        </tr>
        @if($reservation->user->phone)
        <tr>
            <td class="label">Teléfono</td>
            <td class="value">{{ $reservation->user->phone }}</td>
        </tr>
        @endif
    </table>

    <div class="section-title">Detalle de la reserva</div>
    <table>
        <tr>
            <td class="label">Cancha</td>
            <td class="value">{{ $reservation->court?->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Fecha</td>
            <td class="value">{{ $reservation->date_formatted }}</td>
        </tr>
        <tr>
            <td class="label">Horario</td>
            <td class="value">{{ $reservation->start_time_formatted }} – {{ $reservation->end_time_formatted }}</td>
        </tr>
        <tr>
            <td class="label">Duración</td>
            <td class="value">{{ $reservation->duration_hours }} hora(s)</td>
        </tr>
        <tr>
            <td class="label">Estado</td>
            <td class="value"><span class="badge badge-green">Confirmada</span></td>
        </tr>
        <tr>
            <td class="label">Fecha de creación</td>
            <td class="value">{{ $reservation->created_at->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    @if($reservation->items->count() > 0)
    <div class="section-title">Consumibles</div>
    <table>
        @foreach($reservation->items as $item)
        <tr>
            <td class="label">{{ $item->product?->name ?? 'Producto' }} × {{ $item->quantity }}</td>
            <td class="value">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div class="section-title">Resumen de pago</div>
    <table>
        <tr>
            <td class="label">Cancha</td>
            <td class="value">${{ number_format($reservation->court_price, 0, ',', '.') }}</td>
        </tr>
        @if((float)$reservation->consumables_price > 0)
        <tr>
            <td class="label">Consumibles</td>
            <td class="value">${{ number_format($reservation->consumables_price, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr style="border-top: 2px solid #e5e7eb;">
            <td class="label" style="font-weight:700; font-size:13px; padding-top:8px;">Total</td>
            <td class="value" style="font-weight:700; font-size:13px; padding-top:8px;">${{ number_format($reservation->total_price, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if($reservation->notes)
    <div class="section-title">Notas</div>
    <p style="color:#4b5563; font-size:12px;">{{ $reservation->notes }}</p>
    @endif

    <div class="footer">
        <p>{{ $appName }}@if($appPhone) · {{ $appPhone }}@endif</p>
        <p>Documento generado el {{ now()->format('d/m/Y H:i') }} · Guarda este comprobante para presentarlo en el establecimiento.</p>
    </div>
</div>
</body>
</html>
