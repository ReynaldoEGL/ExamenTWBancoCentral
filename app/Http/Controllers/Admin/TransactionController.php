<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(['retiro', 'deposito', 'transferencia'])],
            'node_id' => ['nullable', 'uuid', 'exists:nodes,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $query = DB::table('transactions as t')
            ->leftJoin('nodes as n', 'n.id', '=', 't.node_id')
            ->leftJoin('users_accounts as origen', 'origen.id', '=', 't.cuenta_origen')
            ->leftJoin('users_accounts as destino', 'destino.id', '=', 't.cuenta_destino')
            ->select([
                't.*', 'n.nombre as nodo_nombre', 'n.tipo as nodo_tipo',
                'origen.numero_cuenta as cuenta_origen_numero',
                'destino.numero_cuenta as cuenta_destino_numero',
            ]);

        if (! empty($filters['q'])) {
            $term = trim($filters['q']);
            $query->where(function ($where) use ($term) {
                $where->where('origen.numero_cuenta', 'ilike', '%' . $term . '%')
                    ->orWhere('destino.numero_cuenta', 'ilike', '%' . $term . '%')
                    ->orWhere('n.nombre', 'ilike', '%' . $term . '%');
            });
        }
        if (! empty($filters['tipo'])) $query->where('t.tipo', $filters['tipo']);
        if (! empty($filters['node_id'])) $query->where('t.node_id', $filters['node_id']);
        if (! empty($filters['desde'])) $query->where('t.created_at', '>=', $filters['desde'] . ' 00:00:00');
        if (! empty($filters['hasta'])) $query->where('t.created_at', '<=', $filters['hasta'] . ' 23:59:59.999999');

        $transactions = $query->orderByDesc('t.created_at')->paginate(20)->withQueryString();
        $nodes = DB::table('nodes')->select('id', 'nombre', 'tipo')->orderBy('nombre')->get();
        return view('admin.transactions.index', compact('transactions', 'nodes', 'filters'));
    }
}
