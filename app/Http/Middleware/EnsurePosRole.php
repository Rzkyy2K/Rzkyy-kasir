<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses berdasarkan peran tb_user (mis. super admin,developer).
 * Dipakai untuk area settings agar kasir/admin tidak bisa ubah
 * username/password sendiri maupun hapus akun.
 */
class EnsurePosRole
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role?->nama_role, $roles, true)) {
            abort(403, 'Halaman ini khusus peran: '.implode(', ', $roles).'.');
        }

        return $next($request);
    }
}
