<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NodeController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('nodes as n')
            ->leftJoin('profiles as p', 'p.id', '=', 'n.responsable_id')
            ->select('n.*', 'p.nombre as responsable_perfil');

        if ($request->filled('tipo') && in_array($request->input('tipo'), ['sucursal', 'cajero'], true)) {
            $query->where('n.tipo', $request->input('tipo'));
        }
        if ($request->filled('estado') && in_array($request->input('estado'), ['activo', 'inactivo'], true)) {
            $query->where('n.activo', $request->input('estado') === 'activo');
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($where) use ($term) {
                $where->where('n.nombre', 'ilike', '%' . $term . '%')
                    ->orWhere('n.ubicacion', 'ilike', '%' . $term . '%')
                    ->orWhere('n.responsable_nombre', 'ilike', '%' . $term . '%');
            });
        }

        $nodes = $query->orderByDesc('n.created_at')->paginate(15)->withQueryString();
        return view('admin.nodes.index', compact('nodes'));
    }

    public function create()
    {
        $responsables = $this->responsables();
        return view('admin.nodes.create', compact('responsables'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['sucursal', 'cajero'])],
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'responsable_id' => ['required', 'uuid', 'exists:profiles,id'],
            'responsable_contacto' => ['nullable', 'string', 'max:150'],
            'efectivo_inicial' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ]);

        $profile = DB::table('profiles')->where('id', $data['responsable_id'])->first();
        if (! $profile) {
            return back()->withErrors(['responsable_id' => 'El responsable seleccionado no existe.'])->withInput();
        }
        if ($data['tipo'] === 'sucursal' && $profile->rol === 'ejecutivo' && $profile->node_id !== null) {
            return back()->withErrors(['responsable_id' => 'Ese ejecutivo ya está asociado a una sucursal.'])->withInput();
        }

        $initialCash = Money::fromCents(
            Money::toCents($data['efectivo_inicial'])
        );

    $id = DB::transaction(function () use (
        $data,
        $profile,
        $initialCash,
        $request
    ) {
        // PostgreSQL genera el ID bigint del nodo.
        $id = (string) DB::table('nodes')->insertGetId([
            'tipo' => $data['tipo'],
            'nombre' => trim($data['nombre']),
            'ubicacion' => $data['ubicacion'] ?? null,
            'responsable_nombre' => $profile->nombre,
            'responsable_contacto' => $data['responsable_contacto'] ?? null,
            'responsable_id' => $profile->id,
            'efectivo_disponible' => $initialCash,
            'activo' => true,
        ], 'id');

        // La sucursal puede vincular al ejecutivo con el nodo.
        if ($data['tipo'] === 'sucursal' && $profile->rol === 'ejecutivo') {
            DB::table('profiles')
                ->where('id', $profile->id)
                ->update(['node_id' => $id]);
        }

        // Registrar la asignación inicial de efectivo en la auditoría.
        if (Money::toCents($initialCash) > 0) {
            DB::table('node_cash_movements')->insert([
                'node_id' => $id,
                'monto' => $initialCash,
                'tipo' => 'ingreso',
                'motivo' => 'Asignación inicial al crear el nodo',
                'admin_id' => $request->session()->get('central_admin_id'),
            ]);
        }

    return $id;
});

return redirect()
    ->route('admin.nodes.show', $id)
    ->with('success', 'Nodo creado. Genere su API Key y guárdela de forma segura.');
    }

    public function show(string $nodeId)
    {
        $node = DB::table('nodes as n')
            ->leftJoin('profiles as p', 'p.id', '=', 'n.responsable_id')
            ->where('n.id', $nodeId)
            ->select('n.*', 'p.nombre as responsable_perfil')
            ->first();
        abort_if(! $node, 404);

        $keys = DB::table('api_keys')->where('node_id', $nodeId)->orderByDesc('created_at')->get();
        $cashMovements = DB::table('node_cash_movements as c')
            ->leftJoin('profiles as p', 'p.id', '=', 'c.admin_id')
            ->where('c.node_id', $nodeId)
            ->select('c.*', 'p.nombre as admin_nombre')
            ->orderByDesc('c.created_at')->limit(20)->get();

        return view('admin.nodes.show', compact('node', 'keys', 'cashMovements'));
    }

    public function edit(string $nodeId)
    {
        $node = DB::table('nodes')->where('id', $nodeId)->first();
        abort_if(! $node, 404);
        $responsables = $this->responsables();
        return view('admin.nodes.edit', compact('node', 'responsables'));
    }

    public function update(Request $request, string $nodeId)
    {
        $node = DB::table('nodes')->where('id', $nodeId)->first();
        abort_if(! $node, 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'responsable_id' => ['required', 'uuid', 'exists:profiles,id'],
            'responsable_contacto' => ['nullable', 'string', 'max:150'],
        ]);
        $profile = DB::table('profiles')->where('id', $data['responsable_id'])->first();
        if (! $profile) {
            return back()->withErrors(['responsable_id' => 'El responsable seleccionado no existe.'])->withInput();
        }
        if ($node->tipo === 'sucursal' && $profile->rol === 'ejecutivo' && $profile->node_id !== null && (string) $profile->node_id !== (string) $nodeId) {
            return back()->withErrors(['responsable_id' => 'Ese ejecutivo ya está asociado a otra sucursal.'])->withInput();
        }

        DB::transaction(function () use ($node, $nodeId, $data, $profile, $request) {
            DB::table('nodes')->where('id', $nodeId)->update([
                'nombre' => trim($data['nombre']),
                'ubicacion' => $data['ubicacion'] ?? null,
                'responsable_nombre' => $profile->nombre,
                'responsable_contacto' => $data['responsable_contacto'] ?? null,
                'responsable_id' => $profile->id,
                'activo' => $request->boolean('activo'),
            ]);

            if ($node->tipo === 'sucursal' && $node->responsable_id !== $profile->id) {
                $previous = DB::table('profiles')->where('id', $node->responsable_id)->first();
                if ($previous && $previous->rol === 'ejecutivo' && $previous->node_id !== null && (string) $previous->node_id === (string) $nodeId) {
                    DB::table('profiles')->where('id', $previous->id)->update(['node_id' => null]);
                }
                if ($profile->rol === 'ejecutivo') {
                    DB::table('profiles')->where('id', $profile->id)->update(['node_id' => $nodeId]);
                }
            }
        });

        return redirect()->route('admin.nodes.show', $nodeId)->with('success', 'Datos del nodo actualizados.');
    }

    private function responsables()
    {
        return DB::table('profiles')->select('id', 'nombre', 'rol')->orderBy('nombre')->get();
    }
}
