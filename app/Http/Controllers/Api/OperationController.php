<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiError;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OperationController extends Controller
{
    /** codigo => [status HTTP, mensaje] (catálogo del contrato + IDEMPOTENCY_CONFLICT) */
    private const ERRORES = [
        'NODO_INACTIVO'              => [403, 'El cajero está desactivado'],
        'CUENTA_NO_ENCONTRADA'       => [404, 'La cuenta no existe'],
        'MONTO_INVALIDO'             => [422, 'El monto debe ser mayor a 0'],
        'CUENTA_BLOQUEADA'           => [422, 'La cuenta está bloqueada'],
        'FONDOS_INSUFICIENTES'       => [422, 'Fondos insuficientes'],
        'EFECTIVO_NODO_INSUFICIENTE' => [422, 'El cajero no tiene efectivo suficiente'],
        'IDEMPOTENCY_CONFLICT'       => [422, 'La Idempotency-Key ya se usó con otra operación, cuenta o monto'],
    ];

    public function withdraw(Request $request)
    {
        return $this->ejecutar($request, 'retirar');
    }

    public function deposit(Request $request)
    {
        return $this->ejecutar($request, 'depositar');
    }

    private function ejecutar(Request $request, string $funcion)
    {
        $v = Validator::make($request->all(), [
            'numero_cuenta' => ['required', 'string'],
            'monto'         => ['required', 'numeric'],
        ]);

        if ($v->fails()) {
            return ApiError::make(
                'Los campos numero_cuenta (texto) y monto (número) son obligatorios',
                'DATOS_INVALIDOS',
                400
            );
        }

        // Idempotency-Key: opcional, pero si viene debe ser un UUID válido
        $idemKey = $request->header('Idempotency-Key');
        if ($idemKey !== null) {
            $h = Validator::make(['k' => $idemKey], ['k' => ['uuid']]);
            if ($h->fails()) {
                return ApiError::make(
                    'El header Idempotency-Key debe ser un UUID válido',
                    'DATOS_INVALIDOS',
                    400
                );
            }
            $idemKey = strtolower($idemKey);
        }

        $node = $request->attributes->get('node');

        try {
            // $funcion viene de una constante interna, nunca del usuario
            $row = DB::selectOne(
                "select public.$funcion(?, ?, ?::numeric, ?::uuid) as r",
                [
                    (int) $node->id,
                    $request->input('numero_cuenta'),
                    (string) $request->input('monto'),
                    $idemKey,   // null si no viene el header
                ]
            );
        } catch (QueryException $e) {
            foreach (self::ERRORES as $codigo => [$status, $mensaje]) {
                if (str_contains($e->getMessage(), $codigo)) {
                    return ApiError::make($mensaje, $codigo, $status);
                }
            }
            throw $e;   // lo atrapa el manejador global como ERROR_INTERNO
        }

        return response()->json(json_decode($row->r, true));
    }
}