@extends('emails.layout')

@section('content')
@php
    $r = $reservation->load(['court', 'user']);
    $appUrl = config('app.url');
@endphp

<p class="greeting">Hola, {{ $r->user->name }}</p>
<p class="text">Te informamos que tu reserva ha sido <strong>cancelada</strong>.</p>

<div class="card">
    <div class="card-title">🏟️ Reserva cancelada</div>
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
    @if($r->cancellation_reason)
    <div class="detail-row">
        <span class="detail-label">Motivo</span>
        <span class="detail-value">{{ $r->cancellation_reason }}</span>
    </div>
    @endif
</div>

<div class="alert-box alert-red">
    <p>Los horarios reservados han quedado disponibles nuevamente para otros clientes.</p>
</div>

<p class="text">Si tienes alguna pregunta, no dudes en contactarnos.</p>

<div class="divider"></div>

<a href="{{ $appUrl }}/calendar" class="btn">Hacer una nueva reserva</a>
@endsection
