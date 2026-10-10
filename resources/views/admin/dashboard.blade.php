@extends('layouts.central')
@section('title', 'Resumen')
@section('content')
<div class="page-heading"><div><p class="eyebrow">VISTA GENERAL</p><h1>Resumen del Banco Central</h1></div><a class="button button-primary" href="{{ route('admin.nodes.create') }}">+ Registrar nodo</a></div>
<div class="metric-grid">
    <div class="metric"><span>Sucursales</span><strong>{{ number_format($counts['sucursales']) }}</strong></div>
    <div class="metric"><span>Cajeros</span><strong>{{ number_format($counts['cajeros']) }}</strong></div>
    <div class="metric"><span>Nodos activos</span><strong>{{ number_format($counts['nodos_activos']) }}</strong></div>
    <div class="metric"><span>Cuentas bancarias</span><strong>{{ number_format($counts['cuentas']) }}</strong></div>
    <div class="metric"><span>Saldo agregado de clientes</span><strong>${{ number_format((float)$counts['saldo_clientes'], 2) }}</strong><small>MXN</small></div>
    <div class="metric"><span>Efectivo asignado a nodos</span><strong>${{ number_format((float)$counts['efectivo_nodos'], 2) }}</strong><small>MXN</small></div>
    <div class="metric"><span>Operaciones de hoy</span><strong>{{ number_format($counts['operaciones_hoy']) }}</strong></div>
</div>
<section class="panel"><div class="section-heading"><h2>Transacciones recientes</h2><a href="{{ route('admin.transactions.index') }}">Ver historial completo →</a></div>
<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Cuenta</th><th>Nodo</th><th>Monto</th><th>Saldo resultante</th></tr></thead><tbody>
@forelse($recentTransactions as $tx)
<tr><td>{{ \Illuminate\Support\Carbon::parse($tx->created_at)->format('d/m/Y H:i') }}</td><td><span class="tag tag-{{ $tx->tipo }}">{{ ucfirst($tx->tipo) }}</span></td><td>{{ $tx->cuenta_origen_numero ?? $tx->cuenta_destino_numero ?? '—' }}</td><td>{{ $tx->nodo_nombre ?? 'Sin nodo' }}</td><td>${{ number_format((float)$tx->monto, 2) }}</td><td>{{ $tx->saldo_resultante === null ? '—' : '$'.number_format((float)$tx->saldo_resultante,2) }}</td></tr>
@empty<tr><td colspan="6" class="empty">Todavía no hay transacciones registradas.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
