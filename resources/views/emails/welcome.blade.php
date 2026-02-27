@extends('emails.layout')

@section('content')
@php
    $appUrl = config('app.url');
    $appName = \App\Models\Setting::getValue('court_name', config('app.name'));
@endphp

<p class="greeting">¡Bienvenido/a, {{ $user->name }}! 🎉</p>
<p class="text">Tu cuenta ha sido creada exitosamente en <strong>{{ $appName }}</strong>. Ya puedes empezar a reservar tu cancha favorita.</p>

<div class="card">
    <div class="card-title">✅ Tu cuenta</div>
    <div class="detail-row">
        <span class="detail-label">Nombre</span>
        <span class="detail-value">{{ $user->name }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value">{{ $user->email }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Rol</span>
        <span class="detail-value"><span class="badge badge-green">Cliente</span></span>
    </div>
</div>

<p class="text">Con tu cuenta puedes:</p>
<ul style="padding-left:20px; color:#4b5563; font-size:15px; margin-bottom:20px;">
    <li style="margin-bottom:8px;">📅 Ver disponibilidad y reservar en tiempo real</li>
    <li style="margin-bottom:8px;">🛒 Agregar consumibles a tu reserva</li>
    <li style="margin-bottom:8px;">📋 Ver el historial de todas tus reservas</li>
    <li style="margin-bottom:8px;">❌ Cancelar reservas con anticipación</li>
</ul>

<div class="divider"></div>

<a href="{{ $appUrl }}/calendar" class="btn">¡Reservar ahora!</a>
@endsection
