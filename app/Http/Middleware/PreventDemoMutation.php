<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk Akun Khusus Demo.
 * Akun demo hanya diperbolehkan melihat-lihat tampilan (view-only).
 * Setiap aksi mutasi data (POST, PUT, PATCH, DELETE) akan langsung
 * diblokir dengan pesan: "KAMU HARUS MEMILIKI AKUN UNTUK MENGAKSES FITUR INI".
 */
class PreventDemoMutation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?: auth('web')->user();

        if ($user && ($user->role?->nama_role === 'demo' || $user->username === 'demo')) {
            // Izinkan metode baca data (GET, HEAD, OPTIONS)
            if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                // Izinkan endpoint sesi auth dasar seperti logout & demo-login
                if ($request->is('logout') || $request->is('demo-login')) {
                    return $next($request);
                }

                $message = $request->isMethod('delete')
                    ? 'Akses Dibatasi: Akun Demo tidak memiliki izin untuk menghapus data. Silakan masuk menggunakan akun resmi untuk melakukan tindakan ini.'
                    : 'Akses Dibatasi: Akun Demo hanya memiliki hak akses untuk melihat tampilan. Silakan masuk menggunakan akun resmi untuk mengelola fitur ini.';

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $message,
                    ], 403);
                }

                return back()->with('error', $message);
            }
        }

        return $next($request);
    }
}
