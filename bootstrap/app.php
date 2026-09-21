<?php

use App\Http\Middleware\AllowAccountSwitch;
use App\Http\Middleware\EnsurePosRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai semua reverse proxy / tunnel (localtunnel, ngrok, cloudflared, SSL proxy)
        // agar asset Vite dan URL halaman otomatis menggunakan host & HTTPS dari proxy.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // User yang sudah login dan membuka halaman tamu (login/register)
        // diarahkan ke dashboard.
        $middleware->redirectUsersTo('/dashboard');

        // Izinkan ganti akun lewat form login walau sesi lama masih aktif.
        $middleware->alias([
            'guest' => AllowAccountSwitch::class,
            'pos.role' => EnsurePosRole::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
