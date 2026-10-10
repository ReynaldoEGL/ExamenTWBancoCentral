<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $v = Validator::make($request->query(), [
            'numero_cuenta' => ['nullable', 'string'],
            'tipo'          => ['nullable', 'in:retiro,deposito,transferencia'],
            'desde'         => ['nullable', 'date'],
            'hasta'         => ['nullable', 'date'],
            'page'          => ['nullable', 'integer', 'min:1'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($v->fails()) {
            return ApiError::make('Parámetros de consulta inválidos', 'DATOS_INVALIDOS', 400);
        }

        $node    = $request->attributes->get('node');
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 50);

        $q = DB::table('transactions as t')
            ->leftJoin('users_accounts as o', 'o.id', '=', 't.cuenta_origen')
            ->leftJoin('users_accounts as d', 'd.id', '=', 't.cuenta_destino')
            ->join('nodes as n', 'n.id', '=', 't.node_id');

        // Filtro por cliente
        $cuenta = null;
        if ($numero = $request->query('numero_cuenta')) {
            $cuenta = DB::table('users_accounts')->where('numero_cuenta', $numero)->first();
            if (!$cuenta) {
                return ApiError::make('La cuenta no existe', 'CUENTA_NO_ENCONTRADA', 404);
            }
            $q->where(fn ($w) => $w->where('t.cuenta_origen', $cuenta->id)
                                   ->orWhere('t.cuenta_destino', $cuenta->id));
        }

        // Alcance: una sucursal ve todo el historial de un cliente;
        // en cualquier otro caso, cada nodo ve solo lo suyo.
        if (!($cuenta && $node->tipo === 'sucursal')) {
            $q->where('t.node_id', $node->id);
        }

        if ($t = $request->query('tipo'))  { $q->where('t.tipo', $t); }
        if ($f = $request->query('desde')) { $q->whereDate('t.created_at', '>=', $f); }
        if ($f = $request->query('hasta')) { $q->whereDate('t.created_at', '<=', $f); }

        $total = (clone $q)->count();

        $rows = $q->orderByDesc('t.created_at')->orderByDesc('t.id')
            ->forPage($page, $perPage)
            ->get([
                't.id', 't.tipo', 't.monto', 't.node_id', 't.saldo_resultante', 't.created_at',
                'n.nombre as node_nombre',
                DB::raw('coalesce(o.numero_cuenta, d.numero_cuenta) as numero_cuenta'),
            ]);

        $data = $rows->map(fn ($r) => [
            'id'               => (int) $r->id,
            'tipo'             => $r->tipo,
            'numero_cuenta'    => $r->numero_cuenta,
            'monto'            => (float) $r->monto,
            'node_id'          => (int) $r->node_id,
            'node_nombre'      => $r->node_nombre,
            'saldo_resultante' => $r->saldo_resultante !== null ? (float) $r->saldo_resultante : null,
            'created_at'       => \Carbon\Carbon::parse($r->created_at)->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);

        return response()->json([
            'data'       => $data,
            'paginacion' => ['pagina' => $page, 'por_pagina' => $perPage, 'total' => $total],
        ]);
    }
}
