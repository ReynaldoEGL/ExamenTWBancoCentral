<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;

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
}