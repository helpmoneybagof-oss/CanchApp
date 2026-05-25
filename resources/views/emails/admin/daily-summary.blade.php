@extends('emails.layout')

@section('content')
@php
    $appUrl = config('app.url');
    $total = $reservations->sum('total_price');
    $paid = $reservations->where('payment_status', 'paid')->sum('total_price');
    $pending = $reservations->where('payment_status', 'unpaid')->count();
@endphp

<p class="greeting">Buenos días 🌅</p>
<p class="text">Aquí tienes el resumen de reservas para el <strong>{{ $date }}</strong>.</p>

<div class="card">
    <div class="card-title">📊 Resumen del día</div>
    <div class="detail-row">
        <span class="detail-label">Total de reservas</span>
        <span class="detail-value">{{ $reservations->count() }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Ingresos esperados</span>
        <span class="detail-value">${{ number_format($total, 0, ',', '.') }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Ya pagadas</span>
        <span class="detail-value">${{ number_format($paid, 0, ',', '.') }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Pendientes de cobro</span>
        <span class="detail-value">{{ $pending }}</span>
    </div>
</div>

@if($reservations->count() > 0)
<div class="card">
    <div class="card-title">📋 Reservas del día</div>
    @foreach($reservations as $r)
    <div class="detail-row" style="flex-direction:column; align-items:flex-start; gap:4px;">
        <div style="display:flex; justify-content:space-between; width:100%;">
            <span style="font-size:14px; font-weight:600; color:#111827;">{{ $r->start_time_formatted }} – {{ $r->end_time_formatted }}</span>
            <span class="badge {{ $r->payment_status === 'paid' ? 'badge-green' : 'badge-yellow' }}">
                {{ $r->payment_status === 'paid' ? 'Pagada' : 'Pendiente' }}
            </span>
        </div>
        <span style="font-size:13px; color:#6b7280;">{{ $r->user->name }} · {{ $r->court?->name }} · ${{ number_format($r->total_price, 0, ',', '.') }}</span>
    </div>
    @endforeach
</div>
@else
<div class="alert-box">
    <p>No hay reservas programadas para hoy.</p>
</div>
@endif

<div class="divider"></div>

<a href="{{ $appUrl }}/admin/dashboard" class="btn">Ir al panel admin</a>
@endsection
