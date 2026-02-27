@extends('emails.layout')

@section('content')
@php
    $r = $reservation->load(['court', 'user']);
    $appUrl = config('app.url');
@endphp

<p class="greeting">Reserva cancelada ❌</p>
<p class="text">Una reserva ha sido cancelada en el sistema.</p>

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
</div>

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
    <p>Los slots han sido liberados y están disponibles para nuevas reservas.</p>
</div>

<div class="divider"></div>

<a href="{{ $appUrl }}/admin/reservations" class="btn">Ver todas las reservas</a>
@endsection
