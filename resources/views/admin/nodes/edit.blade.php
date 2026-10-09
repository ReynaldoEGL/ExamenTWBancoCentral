@extends('layouts.central')
@section('title', 'Editar nodo')
@section('content')
<div class="page-heading"><div><p class="eyebrow">INFRAESTRUCTURA</p><h1>Editar {{ $node->nombre }}</h1><p class="muted">El tipo del nodo se conserva para no cambiar su identidad operativa por accidente.</p></div><a class="button button-ghost" href="{{ route('admin.nodes.show', $node->id) }}">← Volver</a></div>
<section class="panel form-panel"><form method="POST" action="{{ route('admin.nodes.update', $node->id) }}" class="stack">@csrf @method('PUT')
<div class="form-grid"><label>Tipo de nodo<input value="{{ ucfirst($node->tipo) }}" disabled></label><label>Nombre<input name="nombre" value="{{ old('nombre', $node->nombre) }}" maxlength="150" required></label><label>Ubicación<input name="ubicacion" value="{{ old('ubicacion', $node->ubicacion) }}" maxlength="255"></label><label>Responsable<select name="responsable_id" required>@foreach($responsables as $person)<option value="{{ $person->id }}" @selected(old('responsable_id', $node->responsable_id)===$person->id)>{{ $person->nombre }} ({{ $person->rol }})</option>@endforeach</select></label><label>Contacto del responsable<input name="responsable_contacto" value="{{ old('responsable_contacto', $node->responsable_contacto) }}" maxlength="150"></label></div>
<label class="checkbox-label"><input type="checkbox" name="activo" value="1" @checked((bool)old('activo', \App\Support\PgBoolean::value($node->activo)))> Nodo activo (desmarcar para impedir que opere mediante la API)</label>
<div class="alert alert-info">El efectivo no se modifica aquí. Use la sección de movimientos de efectivo para conservar la auditoría.</div><div class="actions"><button class="button button-primary">Guardar cambios</button><a class="button button-ghost" href="{{ route('admin.nodes.show', $node->id) }}">Cancelar</a></div>
</form></section>
@endsection
