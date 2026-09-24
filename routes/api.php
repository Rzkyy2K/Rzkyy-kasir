<?php

use App\Http\Controllers\Api\BarangController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\PelangganController;
use App\Http\Controllers\Api\PembelianController;
use App\Http\Controllers\Api\PenjualanController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->get('dashboard', [DashboardController::class, 'index']);

Route::get('sekolah', [MasterController::class, 'sekolah']);
Route::post('sekolah', [MasterController::class, 'storeSekolah']);
Route::put('sekolah/{id}', [MasterController::class, 'updateSekolah']);
Route::delete('sekolah/{id}', [MasterController::class, 'destroySekolah']);
Route::get('katalog', [MasterController::class, 'katalog']);
Route::get('roles', [MasterController::class, 'roles']);
Route::get('user', [MasterController::class, 'users']);
Route::post('user', [MasterController::class, 'storeUser']);
Route::put('user/{id}', [MasterController::class, 'updateUser']);
Route::delete('user/{id}', [MasterController::class, 'destroyUser']);

Route::get('barang', [BarangController::class, 'index']);
Route::get('barang/prediksi-stok', [BarangController::class, 'prediksiStok']);
Route::get('barang/barcode/{barcode}', [BarangController::class, 'byBarcode']);
Route::get('barcode-lookup/{barcode}', [BarangController::class, 'lookupBarcode']);
Route::post('barang', [BarangController::class, 'store']);
Route::get('barang/{id}', [BarangController::class, 'show']);
Route::put('barang/{id}', [BarangController::class, 'update']);
Route::delete('barang/{id}', [BarangController::class, 'destroy']);
Route::post('barang/{id}/stok', [BarangController::class, 'adjustStock']);

Route::get('kelompok-kategori', [KategoriController::class, 'kelompok']);
Route::post('kelompok-kategori', [KategoriController::class, 'storeKelompok']);
Route::put('kelompok-kategori/{id}', [KategoriController::class, 'updateKelompok']);
Route::delete('kelompok-kategori/{id}', [KategoriController::class, 'destroyKelompok']);
Route::post('kelompok-kategori/{id}/pindahkan', [KategoriController::class, 'pindahkanKelompok']);
Route::get('kategori', [KategoriController::class, 'index']);
Route::post('kategori', [KategoriController::class, 'store']);
Route::put('kategori/{id}', [KategoriController::class, 'update']);
Route::delete('kategori/{id}', [KategoriController::class, 'destroy']);

Route::get('supplier', [SupplierController::class, 'index']);
Route::post('supplier', [SupplierController::class, 'store']);
Route::get('supplier/{id}', [SupplierController::class, 'show']);
Route::put('supplier/{id}', [SupplierController::class, 'update']);
Route::delete('supplier/{id}', [SupplierController::class, 'destroy']);

Route::get('kelompok-pelanggan', [PelangganController::class, 'kelompok']);
Route::post('kelompok-pelanggan', [PelangganController::class, 'storeKelompok']);
Route::get('pelanggan', [PelangganController::class, 'index']);
Route::post('pelanggan', [PelangganController::class, 'store']);
Route::put('pelanggan/{id}', [PelangganController::class, 'update']);
Route::delete('pelanggan/{id}', [PelangganController::class, 'destroy']);

Route::get('penjualan/void-requests', [PenjualanController::class, 'pendingVoidRequests']);
Route::get('penjualan', [PenjualanController::class, 'index']);
Route::post('penjualan', [PenjualanController::class, 'store']);
Route::get('penjualan/{id}', [PenjualanController::class, 'show']);
Route::post('penjualan/{id}/request-void', [PenjualanController::class, 'requestVoid']);
Route::post('penjualan/{id}/forward-void', [PenjualanController::class, 'forwardVoid']);
Route::post('penjualan/{id}/approve-void', [PenjualanController::class, 'approveVoid']);
Route::post('penjualan/{id}/reject-void', [PenjualanController::class, 'rejectVoid']);

Route::get('pembelian', [PembelianController::class, 'index']);
Route::post('pembelian', [PembelianController::class, 'store']);
Route::get('pembelian/{id}', [PembelianController::class, 'show']);

Route::get('laporan/penjualan', [LaporanController::class, 'penjualan']);
Route::get('laporan/pembelian', [LaporanController::class, 'pembelian']);
Route::get('laporan/stok', [LaporanController::class, 'stok']);
Route::get('laporan/terlaris', [LaporanController::class, 'terlaris']);
