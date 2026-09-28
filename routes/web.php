<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Models\PosUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Halaman statis About — tema sama dengan Login, tanpa auth.
Route::inertia('about', 'About')->name('about');

// Endpoint login instan untuk Akun Khusus Demo (View-Only mirip role Admin)
Route::post('/demo-login', function (Request $request) {
    $demoRole = DB::table('roles')->where('nama_role', 'demo')->first();
    if (! $demoRole) {
        $idRole = DB::table('roles')->insertGetId([
            'id_role' => 5,
            'nama_role' => 'demo',
        ]);
    } else {
        $idRole = $demoRole->id_role;
    }

    $idSekolah = DB::table('tb_sekolah')->where('is_active', 1)->value('id_sekolah') ?? 1;

    $demoUser = PosUser::firstOrCreate(
        ['username' => 'demo'],
        [
            'id_sekolah' => $idSekolah,
            'id_role' => $idRole,
            'nama_lengkap' => 'Akun Khusus Demo',
            'password' => Hash::make('demo123'),
            'is_active' => true,
        ]
    );

    if (! $demoUser->is_active || $demoUser->id_role !== $idRole) {
        $demoUser->update([
            'is_active' => true,
            'id_role' => $idRole,
        ]);
    }

    Auth::login($demoUser);
    $request->session()->regenerate();

    return redirect()->intended('/dashboard');
})->name('demo.login');

// Endpoint sinkronisasi database server secara instan
Route::get('/setup-db', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    (new \Database\Seeders\PosDataSeeder())->run();
    return response()->json([
        'status' => 'success',
        'message' => 'Database POS berhasil di-setup dan di-seed!',
        'total_user' => \Illuminate\Support\Facades\DB::table('tb_user')->count(),
        'total_sekolah' => \Illuminate\Support\Facades\DB::table('tb_sekolah')->count(),
        'total_barang' => \Illuminate\Support\Facades\DB::table('tb_barang')->count(),
    ]);
});

// Halaman awal = login. Sudah login → langsung ke dashboard.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('pos.dashboard')
        : redirect()->route('login');
})->name('home');

// Sesi login (akun tb_user) untuk mengunci sekolah & peran di frontend.
Route::middleware('auth')->get('/me', function (Request $request) {
    return response()->json($request->user()->load(['role', 'sekolah']));
})->name('me');

// EduMart POS — Vue 3 + Axios → Laravel REST API → MySQL db_rizky.
Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('pos.dashboard');
    Route::inertia('kasir', 'Kasir')->name('pos.kasir');
    Route::inertia('produk', 'Produk')->name('pos.produk');
    Route::inertia('stok', 'Stok')->name('pos.stok');
    Route::inertia('kategori', 'Kategori')->name('pos.kategori');
    Route::inertia('pembelian', 'Pembelian')->name('pos.pembelian');
    Route::inertia('penjualan', 'Penjualan')->name('pos.penjualan');
    Route::inertia('supplier', 'Supplier')->name('pos.supplier');
    Route::inertia('pelanggan', 'Pelanggan')->name('pos.pelanggan');
    Route::inertia('laporan', 'Laporan')->name('pos.laporan');
    Route::inertia('users', 'Users')->name('pos.users');
    Route::inertia('pengaturan', 'Pengaturan')->name('pos.pengaturan');
});

require __DIR__.'/settings.php';
