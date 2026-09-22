<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_user` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_user')) {
            return;
        }

        Schema::create('tb_user', function (Blueprint $table) {
            $table->integer('id_user', true);
            $table->integer('id_sekolah')->nullable();
            $table->integer('id_role')->nullable();
            $table->string('username', 50)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('nama_lengkap', 100)->nullable();
            $table->boolean('is_active')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah');
            $table->foreign('id_role')
                ->references('id_role')
                ->on('roles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_user');
    }
};
