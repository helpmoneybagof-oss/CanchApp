@extends('emails.layout')

@section('content')
@php
    $r = $reservation->load(['court', 'user', 'items.product']);
    $appUrl = config('app.url');
@endphp

<p class="greeting">Nueva reserva recibida 🎉</p>
<p class="text">Se ha creado una nueva reserva en el sistema.</p>

<div class="card">
    <div class="card-title">👤 Cliente</div>
    <div class="detail-row">
        <span class="detail-label">Nombre</span>
        <span class="detail-value">{{ $r->user->name }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value">{{ $r->user->email }}</span>
    </div>
    @if($r->user->phone)
    <div class="detail-row">
        <span class="detail-label">Teléfono</span>
        <span class="detail-value">{{ $r->user->phone }}</span>
    </div>
    @endif
</div>

<div class="card">
    <div class="card-title">🏟️ Detalle de la reserva</div>
    <div class="detail-row">
        <span class="detail-label">Código</span>
        <span class="detail-value">{{ $r->confirmation_code }}</span>
    </div>
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
        <span class="detail-label">Total</span>
        <span class="detail-value">${{ number_format($r->total_price, 0, ',', '.') }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Pago</span>
        <span class="detail-value"><span class="badge badge-yellow">Pendiente</span></span>
    </div>
</div>

@if($r->items->count() > 0)
<div class="card">
    <div class="card-title">🛒 Consumibles pedidos</div>
    @foreach($r->items as $item)
    <div class="detail-row">
        <span class="detail-label">{{ $item->product?->name ?? 'Producto' }} × {{ $item->quantity }}</span>
        <span class="detail-value">${{ number_format($item->subtotal, 0, ',', '.') }}</span>
    </div>
    @endforeach
</div>
@endif

@if($r->notes)
<div class="alert-box">
    <p><strong>📝 Notas del cliente:</strong> {{ $r->notes }}</p>
</div>
@endif

<div class="divider"></div>

<a href="{{ $appUrl }}/admin/reservations/{{ $r->id }}" class="btn">Ver en el panel admin</a>
@endsection
