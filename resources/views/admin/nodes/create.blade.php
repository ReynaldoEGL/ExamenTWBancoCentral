@extends('layouts.central')
@section('title', 'Registrar nodo')
@section('content')
<div class="page-heading"><div><p class="eyebrow">INFRAESTRUCTURA</p><h1>Registrar un nodo</h1><p class="muted">El efectivo inicial se registra también en la auditoría de movimientos.</p></div><a class="button button-ghost" href="{{ route('admin.nodes.index') }}">← Volver</a></div>
<section class="panel form-panel"><form method="POST" action="{{ route('admin.nodes.store') }}" class="stack">@csrf
<div class="form-grid"><label>Tipo de nodo<select name="tipo" required><option value="">Seleccione</option><option value="sucursal" @selected(old('tipo')==='sucursal')>Sucursal</option><option value="cajero" @selected(old('tipo')==='cajero')>Cajero automático</option></select></label><label>Nombre<input name="nombre" value="{{ old('nombre') }}" maxlength="150" required></label><label>Ubicación<input name="ubicacion" value="{{ old('ubicacion') }}" maxlength="255"></label><label>Responsable<select name="responsable_id" required><option value="">Seleccione un perfil</option>@foreach($responsables as $person)<option value="{{ $person->id }}" @selected(old('responsable_id')===$person->id)>{{ $person->nombre }} ({{ $person->rol }})</option>@endforeach</select></label><label>Contacto del responsable<input name="responsable_contacto" value="{{ old('responsable_contacto') }}" maxlength="150"></label><label>Efectivo inicial (MXN)<input type="number" name="efectivo_inicial" min="0" step="0.01" value="{{ old('efectivo_inicial', '0.00') }}" required></label></div>
@if($responsables->isEmpty())<div class="alert alert-warning">No hay perfiles en <code>public.profiles</code>. Pida al responsable de Supabase que cree el perfil de un administrador o ejecutivo antes de registrar nodos.</div>@endif
<div class="actions"><button class="button button-primary" type="submit">Crear nodo</button><a class="button button-ghost" href="{{ route('admin.nodes.index') }}">Cancelar</a></div>
</form></section>
@endsection
