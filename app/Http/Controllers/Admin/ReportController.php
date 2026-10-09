<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        $desde = $validated['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $validated['hasta'] ?? now()->toDateString();
        $startAt = $desde . ' 00:00:00';
        $endAt = $hasta . ' 23:59:59.999999';

        $summary = DB::table('transactions')->whereBetween('created_at', [$startAt, $endAt])
            ->selectRaw("count(*) as total_operaciones,
                coalesce(sum(case when tipo = 'retiro' then monto else 0 end), 0) as importe_retiros,
                coalesce(sum(case when tipo = 'deposito' then monto else 0 end), 0) as importe_depositos,
                coalesce(sum(case when tipo = 'transferencia' then monto else 0 end), 0) as importe_transferencias")
            ->first();

        $byNode = DB::table('transactions as t')
            ->join('nodes as n', 'n.id', '=', 't.node_id')
            ->whereBetween('t.created_at', [$startAt, $endAt])
            ->groupBy('n.id', 'n.nombre', 'n.tipo')
            ->selectRaw("n.id, n.nombre, n.tipo, count(*) as operaciones,
                coalesce(sum(case when t.tipo = 'retiro' then t.monto else 0 end),0) as retiros,
                coalesce(sum(case when t.tipo = 'deposito' then t.monto else 0 end),0) as depositos")
            ->orderByDesc('operaciones')->get();

        $cashByNode = DB::table('nodes')->select('id', 'nombre', 'tipo', 'activo', 'efectivo_disponible')
            ->orderBy('tipo')->orderBy('nombre')->get();

        return view('admin.reports.index', compact('summary', 'byNode', 'cashByNode', 'desde', 'hasta'));
    }
}
