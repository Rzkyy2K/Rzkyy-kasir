<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_detail_penjualan` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_detail_penjualan')) {
            return;
        }

        Schema::create('tb_detail_penjualan', function (Blueprint $table) {
            $table->integer('id_detail_penjualan', true);
            $table->integer('id_penjualan')->nullable();
            $table->integer('id_barang')->nullable();
            $table->integer('jumlah_barang')->nullable();
            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->decimal('harga_jual', 12, 2)->nullable();
            $table->decimal('diskon_tipe', 12, 2)->nullable();
            $table->decimal('diskon_nilai', 12, 2)->nullable();
            $table->decimal('diskon_nominal', 14, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();

            $table->foreign('id_penjualan')
                ->references('id_penjualan')
                ->on('tb_penjualan');
            $table->foreign('id_barang')
                ->references('id_barang')
                ->on('tb_barang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_detail_penjualan');
    }
};
