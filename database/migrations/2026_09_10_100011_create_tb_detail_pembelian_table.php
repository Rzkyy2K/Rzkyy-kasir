<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_detail_pembelian` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_detail_pembelian')) {
            return;
        }

        Schema::create('tb_detail_pembelian', function (Blueprint $table) {
            $table->integer('id_detail_pembelian', true);
            $table->integer('id_pembelian')->nullable();
            $table->integer('id_barang')->nullable();
            $table->string('satuan', 20)->nullable();
            $table->integer('jumlah')->nullable();
            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();

            $table->foreign('id_pembelian')
                ->references('id_pembelian')
                ->on('tb_pembelian');
            $table->foreign('id_barang')
                ->references('id_barang')
                ->on('tb_barang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_detail_pembelian');
    }
};
