<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use symfony\Component\HttpFoundation\Response;  

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-API-Key');

        if (!$key) {
            return ApiError::make('API Key inválida', 'API_KEY_INVALIDA', 401);
        }

        $node = DB::selectOne(
            'select n.id, n.nombre, n.tipo, n.activo, n.responsable_nombre, n.efectivo_disponible
               from api_keys k
               join nodes n on n.id = k.node_id
              where k.key_hash = ? and k.activa',
            [hash('sha256', $key)]
        );

        if (!$node) {
            return ApiError::make('API Key inválida', 'API_KEY_INVALIDA', 401);
        }

        if (!$node->activo) {
            return ApiError::make('El cajero está desactivado', 'NODO_INACTIVO', 403);
        }

        $request->attributes->set('node', $node);

        return $next($request);
    }
}