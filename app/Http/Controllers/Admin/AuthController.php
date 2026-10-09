<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function create(Request $request)
    {
        if ($request->session()->has('central_admin_id')) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.central-login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $supabaseUrl = rtrim((string) config('services.supabase.url'), '/');
        $publicKey = (string) config('services.supabase.key');

        if ($supabaseUrl === '' || $publicKey === '') {
            return back()->withErrors([
                'email' => 'Falta configurar SUPABASE_URL o SUPABASE_PUBLIC_KEY en el entorno del servidor.',
            ])->onlyInput('email');
        }

        try {
            // Supabase Auth comprueba la contraseña; Laravel nunca la guarda.
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['apikey' => $publicKey])
                ->timeout(10)
                ->post($supabaseUrl . '/auth/v1/token?grant_type=password', [
                    'email' => $credentials['email'],
                    'password' => $credentials['password'],
                ]);
        } catch (ConnectionException $exception) {
            report($exception);
            return back()->withErrors([
                'email' => 'No fue posible contactar al servicio de autenticación. Verifique la conexión e inténtelo de nuevo.',
            ])->onlyInput('email');
        }

        if (! $response->successful()) {
            return back()->withErrors([
                'email' => 'Correo o contraseña incorrectos, o usuario sin acceso al proyecto.',
            ])->onlyInput('email');
        }

        $authUserId = data_get($response->json(), 'user.id');
        if (! is_string($authUserId) || $authUserId === '') {
            return back()->withErrors(['email' => 'Supabase Auth no devolvió una identidad válida.'])->onlyInput('email');
        }

        // Se comprueba el rol en la tabla profiles del proyecto central.
        $profile = DB::table('profiles')
            ->where('id', $authUserId)
            ->where('rol', 'admin')
            ->first();

        if (! $profile) {
            return back()->withErrors([
                'email' => 'La cuenta existe, pero no tiene el rol admin en public.profiles.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->put([
            'central_admin_id' => $profile->id,
            'central_admin_name' => $profile->nombre,
            'central_admin_email' => $credentials['email'],
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        // La sesión local se destruye. La contraseña ni los tokens de Supabase se guardan.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('central.login')->with('success', 'La sesión se cerró correctamente.');
    }
}
