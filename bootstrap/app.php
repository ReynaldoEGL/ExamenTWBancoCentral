<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));

    $exceptions->render(function (\Throwable $e, $request) {
        if (!$request->is('api/*')) {
            return null;
        }

        $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            ? $e->getStatusCode()
            : 500;

        if ($status >= 500) {
            return \App\Support\ApiError::make('Error interno del servidor', 'ERROR_INTERNO', 500);
        }

        return \App\Support\ApiError::make('Petición inválida', 'DATOS_INVALIDOS', $status);
    });
})->create();
