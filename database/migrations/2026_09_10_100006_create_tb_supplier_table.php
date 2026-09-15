<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_supplier` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_supplier')) {
            return;
        }

        Schema::create('tb_supplier', function (Blueprint $table) {
            $table->integer('id_supplier', true);
            $table->primary('id_supplier');
            $table->integer('id_sekolah')->nullable();
            $table->string('nama', 100)->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->text('alamat_supplier')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->boolean('is_delete')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_supplier');
    }
};
