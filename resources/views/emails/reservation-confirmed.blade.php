@extends('emails.layout')

@section('content')
@php
    $r = $reservation->load(['court', 'items.product', 'user']);
    $appUrl = config('app.url');
@endphp

<p class="greeting">¡Hola, {{ $r->user->name }}! 🎉</p>
<p class="text">Tu reserva ha sido <strong>confirmada exitosamente</strong>. Aquí tienes el resumen:</p>

<div class="confirmation-code">
    {{ $r->confirmation_code }}
</div>
<p style="text-align:center; font-size:13px; color:#6b7280; margin-bottom:24px;">Código de confirmación</p>

<div class="card">
    <div class="card-title">🏟️ Detalle de la reserva</div>
    <div class="detail-row">
        <span class="detail-label">Cancha</span>
        <span class="detail-value">{{ $r->court?->name ?? 'N/A' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Fecha</span>
        <span class="detail-value">{{ $r->date_formatted }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Horario</span>
        <span class="detail-value">{{ $r->start_time_formatted }} – {{ $r->end_time_formatted }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Duración</span>
        <span class="detail-value">{{ $r->duration_hours }} hora(s)</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Estado de pago</span>
        <span class="detail-value">
            <span class="badge badge-yellow">Pendiente de pago</span>
        </span>
    </div>
</div>

@if($r->items->count() > 0)
<div class="card">
    <div class="card-title">🛒 Consumibles</div>
    @foreach($r->items as $item)
    <div class="detail-row">
        <span class="detail-label">{{ $item->product?->name ?? 'Producto' }} × {{ $item->quantity }}</span>
        <span class="detail-value">${{ number_format($item->subtotal, 0, ',', '.') }}</span>
    </div>
    @endforeach
</div>
@endif

<div class="card">
    <div class="card-title">💰 Resumen de pago</div>
    <div class="detail-row">
        <span class="detail-label">Cancha</span>
        <span class="detail-value">${{ number_format($r->court_price, 0, ',', '.') }}</span>
    </div>
    @if((float)$r->consumables_price > 0)
    <div class="detail-row">
        <span class="detail-label">Consumibles</span>
        <span class="detail-value">${{ number_format($r->consumables_price, 0, ',', '.') }}</span>
    </div>
    @endif
    <div class="total-row">
        <span>Total</span>
        <span>${{ number_format($r->total_price, 0, ',', '.') }}</span>
    </div>
</div>

@if($r->notes)
<div class="alert-box">
    <p><strong>📝 Notas:</strong> {{ $r->notes }}</p>
</div>
@endif

<p class="text" style="margin-top:24px;">El pago se realiza en el establecimiento al momento de usar la cancha. Puedes ver el comprobante PDF adjunto.</p>

<div class="divider"></div>

<a href="{{ $appUrl }}/reservations/{{ $r->id }}" class="btn">Ver mi reserva</a>
@endsection
