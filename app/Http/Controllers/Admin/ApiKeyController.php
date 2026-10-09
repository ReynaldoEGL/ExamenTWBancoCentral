<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Support\PgBoolean;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function store(string $nodeId)
    {
        $node = DB::table('nodes')->where('id', $nodeId)->first();
        abort_if(! $node, 404);
        if (! PgBoolean::value($node->activo)) {
            return back()->withErrors(['api_key' => 'Active el nodo antes de generar una API Key.']);
        }

        // La key completa se muestra una sola vez. La base de datos conserva solo SHA-256.
        $plainKey = ($node->tipo === 'cajero' ? 'atm_' : 'suc_') . Str::random(48);
        $keyHash = hash('sha256', $plainKey);
        $keyPrefix = substr($plainKey, 0, 12);

        DB::transaction(function () use ($nodeId, $plainKey, $keyHash, $keyPrefix) {
            $lockedNode = DB::table('nodes')->where('id', $nodeId)->lockForUpdate()->first();
            abort_if(! $lockedNode, 404);
            if (! PgBoolean::value($lockedNode->activo)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['api_key' => 'El nodo está inactivo.']);
            }

            // El índice parcial permite una sola key activa por nodo.
            DB::table('api_keys')->where('node_id', $nodeId)->where('activa', true)->update([
                'activa' => false,
                'revoked_at' => now(),
            ]);

            DB::table('api_keys')->insert([
                'id' => (string) Str::uuid(),
                'node_id' => $nodeId,
                'key_hash' => $keyHash,
                'key_prefix' => $keyPrefix,
                'activa' => true,
            ]);
        });

        return redirect()->route('admin.nodes.show', $nodeId)
            ->with('success', 'API Key generada. Cópiela ahora: no se puede recuperar desde la base de datos.')
            ->with('new_api_key', $plainKey);
    }

    public function revoke(string $nodeId, string $keyId)
    {
        $nodeExists = DB::table('nodes')->where('id', $nodeId)->exists();
        abort_if(! $nodeExists, 404);

        $affected = DB::table('api_keys')
            ->where('id', $keyId)
            ->where('node_id', $nodeId)
            ->where('activa', true)
            ->update(['activa' => false, 'revoked_at' => now()]);

        return redirect()->route('admin.nodes.show', $nodeId)->with(
            $affected ? 'success' : 'warning',
            $affected ? 'API Key revocada.' : 'La clave ya estaba revocada o no pertenece a este nodo.'
        );
    }
}
