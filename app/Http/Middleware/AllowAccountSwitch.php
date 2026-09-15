<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seperti middleware "guest" bawaan, kecuali untuk POST /login:
 * pengguna yang masih login boleh masuk dengan akun lain (ganti akun).
 * Tanpa ini, submit form login saat sesi lama aktif akan dipantulkan
 * dan pengguna tetap memakai sekolah/peran akun lama.
 */
class AllowAccountSwitch extends RedirectIfAuthenticated
{
    /**
     * @param  array<int, string>  $guards
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->is('login') && $request->isMethod('post')) {
            $guard = Auth::guard($guards[0] ?? null);

            if ($guard->check()) {
                $guard->logout();
            }

            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
