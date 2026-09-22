<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_kelompok_pelanggan` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_kelompok_pelanggan')) {
            return;
        }

        Schema::create('tb_kelompok_pelanggan', function (Blueprint $table) {
            $table->integer('id_kelompok_pelanggan', true);
            $table->integer('id_sekolah')->nullable();
            $table->string('nama_kelompok', 100)->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_kelompok_pelanggan');
    }
};
