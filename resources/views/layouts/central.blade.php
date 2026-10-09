<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Banco Central') · Sistema Bancario</title>
    <link rel="stylesheet" href="{{ asset('css/central-admin.css') }}">
</head>
<body>
@if(session()->has('central_admin_id'))
<header class="topbar">
    <a class="brand" href="{{ route('admin.dashboard') }}">Banco Central <span>Panel administrativo</span></a>
    <nav class="nav">
        <a href="{{ route('admin.dashboard') }}">Resumen</a>
        <a href="{{ route('admin.nodes.index') }}">Nodos</a>
        <a href="{{ route('admin.transactions.index') }}">Transacciones</a>
        <a href="{{ route('admin.reports.index') }}">Reportes</a>
    </nav>
    <div class="user-menu"><span>{{ session('central_admin_name') }}</span>
        <form method="POST" action="{{ route('central.logout') }}">@csrf<button class="button button-ghost" type="submit">Cerrar sesión</button></form>
    </div>
</header>
@endif
<main class="container">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-error"><strong>Revisa los datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="footer">Sistema Bancario Distribuido · Banco Central</footer>
</body>
</html>
