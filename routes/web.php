<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Halaman statis About — tema sama dengan Login, tanpa auth.
Route::inertia('about', 'About')->name('about');

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
