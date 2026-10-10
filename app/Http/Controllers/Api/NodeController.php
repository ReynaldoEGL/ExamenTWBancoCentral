<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function me(Request $request)
    {
        $n = $request->attributes->get('node');

        return response()->json([
            'id'                  => (int) $n->id,
            'nombre'              => $n->nombre,
            'tipo'                => $n->tipo,
            'estado'              => $n->activo ? 'activo' : 'inactivo',
            'responsable'         => $n->responsable_nombre,
            'efectivo_disponible' => (float) $n->efectivo_disponible,
        ]);
    }
}