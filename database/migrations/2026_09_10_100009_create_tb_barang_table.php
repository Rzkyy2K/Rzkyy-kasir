<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_barang` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_barang')) {
            return;
        }

        Schema::create('tb_barang', function (Blueprint $table) {
            $table->integer('id_barang', true);
            $table->integer('id_sekolah')->nullable();
            $table->string('barcode', 50)->nullable();
            $table->string('nama', 150)->nullable();
            $table->integer('id_kategori')->nullable();
            $table->integer('id_kelompok_kategori')->nullable();
            $table->integer('id_supplier')->nullable();
            $table->string('satuan', 20)->nullable();
            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->decimal('harga_jual', 12, 2)->nullable();
            $table->integer('stok')->nullable();
            $table->boolean('is_active')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->integer('is_delete')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah');
            $table->foreign('id_kategori')
                ->references('id_kategori')
                ->on('tb_kategori');
            $table->foreign('id_kelompok_kategori')
                ->references('id_kelompok')
                ->on('tb_kelompok_kategori');
            $table->foreign('id_supplier')
                ->references('id_supplier')
                ->on('tb_supplier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_barang');
    }
};
