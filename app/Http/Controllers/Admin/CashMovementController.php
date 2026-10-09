<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashMovementController extends Controller
{
    public function store(Request $request, string $nodeId)
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['ingreso', 'egreso'])],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($data, $nodeId, $request) {
                $node = DB::table('nodes')->where('id', $nodeId)->lockForUpdate()->first();
                abort_if(! $node, 404);

                $currentCents = Money::toCents($node->efectivo_disponible);
                $movementCents = Money::toCents($data['monto']);
                if ($data['tipo'] === 'egreso' && $movementCents > $currentCents) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'monto' => 'El egreso no puede ser mayor que el efectivo actualmente asignado.',
                    ]);
                }

                $newCents = $data['tipo'] === 'ingreso'
                    ? $currentCents + $movementCents
                    : $currentCents - $movementCents;

                DB::table('nodes')->where('id', $nodeId)->update([
                    'efectivo_disponible' => Money::fromCents($newCents),
                ]);

                DB::table('node_cash_movements')->insert([
                    'node_id' => $nodeId,
                    'monto' => Money::fromCents($movementCents),
                    'tipo' => $data['tipo'],
                    'motivo' => $data['motivo'] ?? null,
                    'admin_id' => $request->session()->get('central_admin_id'),
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }

        return redirect()->route('admin.nodes.show', $nodeId)->with('success', 'Movimiento de efectivo registrado.');
    }
}
