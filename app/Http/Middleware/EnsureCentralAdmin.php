<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('central_admin_id');
        if (! $adminId) {
            return redirect()->route('central.login');
        }

        // Revalidar el rol impide conservar el acceso si el perfil deja de ser admin.
        $isAdmin = DB::table('profiles')
            ->where('id', $adminId)
            ->where('rol', 'admin')
            ->exists();

        if (! $isAdmin) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('central.login')->withErrors([
                'email' => 'La cuenta ya no tiene permisos de administrador.',
            ]);
        }

        return $next($request);
    }
}
