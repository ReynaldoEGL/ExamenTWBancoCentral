<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $counts = [
            'sucursales' => DB::table('nodes')->where('tipo', 'sucursal')->count(),
            'cajeros' => DB::table('nodes')->where('tipo', 'cajero')->count(),
            'nodos_activos' => DB::table('nodes')->where('activo', true)->count(),
            'cuentas' => DB::table('users_accounts')->count(),
            'saldo_clientes' => DB::table('users_accounts')->sum('saldo'),
            'efectivo_nodos' => DB::table('nodes')->sum('efectivo_disponible'),
            'operaciones_hoy' => DB::table('transactions')->whereDate('created_at', now()->toDateString())->count(),
        ];

        $recentTransactions = DB::table('transactions as t')
            ->leftJoin('nodes as n', 'n.id', '=', 't.node_id')
            ->leftJoin('users_accounts as origen', 'origen.id', '=', 't.cuenta_origen')
            ->leftJoin('users_accounts as destino', 'destino.id', '=', 't.cuenta_destino')
            ->select([
                't.id', 't.tipo', 't.monto', 't.saldo_resultante', 't.created_at',
                'n.nombre as nodo_nombre', 'origen.numero_cuenta as cuenta_origen_numero',
                'destino.numero_cuenta as cuenta_destino_numero',
            ])
            ->orderByDesc('t.created_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('counts', 'recentTransactions'));
    }
}
