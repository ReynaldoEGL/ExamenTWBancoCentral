<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    public function show(string $numero)
    {
        $c = DB::selectOne(
            'select numero_cuenta, titular, saldo, estado from users_accounts where numero_cuenta = ?',
            [$numero]
        );

        if (!$c) {
            return ApiError::make('La cuenta no existe', 'CUENTA_NO_ENCONTRADA', 404);
        }

        return response()->json([
            'numero_cuenta' => $c->numero_cuenta,
            'titular'       => $c->titular,
            'saldo'         => (float) $c->saldo,
            'estado'        => $c->estado,
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'titular'       => ['required', 'string', 'max:150'],
            'saldo_inicial' => ['nullable', 'numeric'],
        ]);

        if ($v->fails()) {
            return ApiError::make('El campo titular es obligatorio y saldo_inicial debe ser numérico', 'DATOS_INVALIDOS', 400);
        }

        $node = $request->attributes->get('node');

        try {
            $row = DB::selectOne(
                'select public.crear_cuenta(?, ?, ?::numeric) as r',
                [(int) $node->id, $request->input('titular'), (string) $request->input('saldo_inicial', 0)]
            );
        } catch (QueryException $e) {
            $errores = [
                'NODO_INACTIVO'      => [403, 'La sucursal está desactivada'],
                'NODO_NO_AUTORIZADO' => [403, 'Este nodo no puede crear cuentas'],
                'DATOS_INVALIDOS'    => [400, 'El titular es obligatorio'],
                'MONTO_INVALIDO'     => [422, 'El saldo inicial no puede ser negativo'],
            ];
            foreach ($errores as $codigo => [$status, $mensaje]) {
                if (str_contains($e->getMessage(), $codigo)) {
                    return ApiError::make($mensaje, $codigo, $status);
                }
            }
            throw $e;
        }

        return response()->json(json_decode($row->r, true), 201);
    }

}