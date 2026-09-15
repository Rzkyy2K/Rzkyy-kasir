<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_pelanggan` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_pelanggan')) {
            return;
        }

        Schema::create('tb_pelanggan', function (Blueprint $table) {
            $table->integer('id_pelanggan', true);
            $table->primary('id_pelanggan');
            $table->integer('id_kelompok_pelanggan')->nullable();
            $table->string('nama_pelanggan', 150)->nullable();
            $table->string('telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->boolean('is_delete')->nullable();

            $table->foreign('id_kelompok_pelanggan')
                ->references('id_kelompok_pelanggan')
                ->on('tb_kelompok_pelanggan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_pelanggan');
    }
};
