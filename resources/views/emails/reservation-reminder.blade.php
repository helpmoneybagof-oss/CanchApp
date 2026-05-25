@extends('emails.layout')

@section('content')
@php
    $r = $reservation->load(['court', 'user']);
    $appUrl = config('app.url');
@endphp

<p class="greeting">¡Hola, {{ $r->user->name }}! ⏰</p>
<p class="text">Te recordamos que tienes una reserva programada en <strong>{{ $hoursLabel }}</strong>.</p>

<div class="card">
    <div class="card-title">🏟️ Tu próxima reserva</div>
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
        <span class="detail-label">Total a pagar</span>
        <span class="detail-value">${{ number_format($r->total_price, 0, ',', '.') }}</span>
    </div>
</div>

@if(\App\Models\Setting::getValue('court_address'))
<div class="alert-box">
    <p>📍 <strong>Dirección:</strong> {{ \App\Models\Setting::getValue('court_address') }}</p>
</div>
@endif

<p class="text">¡Te esperamos! Recuerda llegar unos minutos antes.</p>

<div class="divider"></div>

<a href="{{ $appUrl }}/reservations/{{ $r->id }}" class="btn">Ver detalle de reserva</a>
@endsection
