<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Relasi ke tb_sekolah
            $table->integer('id_sekolah')->nullable();

            // Relasi ke roles
            $table->integer('id_role')->nullable();

            $table->string('username', 50);
            $table->string('password', 255);
            $table->string('nama_lengkap', 100);

            $table->boolean('is_active');

            $table->timestamp('created_at');
            $table->integer('created_by');

            $table->timestamp('updated_at');
            $table->integer('updated_by');

            $table->timestamp('deleted_at');
            $table->integer('deleted_by');

            // Mengikuti unique key dari tb_user
            $table->unique(['id_sekolah', 'id_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};