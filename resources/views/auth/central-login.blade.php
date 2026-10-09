@extends('layouts.central')
@section('title', 'Iniciar sesión')
@section('content')
<div class="login-wrap">
    <section class="panel login-panel">
        <p class="eyebrow">ACCESO ADMINISTRATIVO</p>
        <h1>Banco Central</h1>
        <p class="muted">Inicie sesión con el usuario administrador creado en Supabase Auth.</p>
        <form method="POST" action="{{ route('central.login.store') }}" class="stack">
            @csrf
            <label>Correo electrónico<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
            <label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="button button-primary" type="submit">Iniciar sesión</button>
        </form>
        <p class="hint">No se crea una cuenta nueva desde esta pantalla. La cuenta debe existir en Supabase Auth y tener rol <code>admin</code> en <code>public.profiles</code>.</p>
    </section>
</div>
@endsection
