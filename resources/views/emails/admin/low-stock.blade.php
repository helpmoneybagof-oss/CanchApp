@extends('emails.layout')

@section('content')
@php
    $appUrl = config('app.url');
@endphp

<p class="greeting">⚠️ Alerta de stock bajo</p>
<p class="text">Los siguientes productos tienen stock igual o inferior al mínimo configurado. Te recomendamos reabastecer pronto.</p>

<div class="card">
    <div class="card-title">📦 Productos con stock bajo</div>
    @foreach($products as $product)
    <div class="detail-row">
        <span class="detail-label">{{ $product->name }}</span>
        <span class="detail-value">
            <span class="badge badge-red">Stock: {{ $product->stock }} / Mín: {{ $product->min_stock }}</span>
        </span>
    </div>
    @endforeach
</div>

<div class="alert-box alert-red">
    <p>Recuerda actualizar el inventario antes de que los productos se agoten y afecten las reservas.</p>
</div>

<div class="divider"></div>

<a href="{{ $appUrl }}/admin/products" class="btn">Ver inventario</a>
@endsection
