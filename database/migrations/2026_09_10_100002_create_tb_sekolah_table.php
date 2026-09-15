<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_sekolah` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_sekolah')) {
            return;
        }

        Schema::create('tb_sekolah', function (Blueprint $table) {
            $table->integer('id_sekolah', true);
            $table->primary('id_sekolah');
            $table->string('kode_sekolah', 20);
            $table->string('nama_sekolah', 150);
            $table->text('alamat_sekolah')->nullable();
            $table->string('website', 200)->nullable();
            $table->boolean('is_active')->nullable()->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->string('alamat', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_sekolah');
    }
};
