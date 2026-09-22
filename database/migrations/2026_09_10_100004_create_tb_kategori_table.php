<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_kategori` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_kategori')) {
            return;
        }

        Schema::create('tb_kategori', function (Blueprint $table) {
            $table->integer('id_kategori', true);
            $table->integer('id_kelompok')->nullable();
            $table->string('nama', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->tinyInteger('is_deleted')->nullable();

            $table->foreign('id_kelompok')
                ->references('id_kelompok')
                ->on('tb_kelompok_kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_kategori');
    }
};
