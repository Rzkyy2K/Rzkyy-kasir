<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Deteksi jika diakses lewat Tunnel (localtunnel / ngrok / cloudflare)
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
            if ($proto === 'https') {
                URL::forceScheme('https');
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
                URL::forceRootUrl("{$proto}://{$_SERVER['HTTP_X_FORWARDED_HOST']}");
            }
        }

        $this->configureDefaults();

        // Otomatis seed data awal kasir bila tb_user kosong (misal di SQLite server)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('tb_user') && \Illuminate\Support\Facades\DB::table('tb_user')->count() === 0) {
                (new \Database\Seeders\PosDataSeeder())->run();
            }
        } catch (\Throwable $e) {
            // Lewati bila koneksi DB belum siap
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
