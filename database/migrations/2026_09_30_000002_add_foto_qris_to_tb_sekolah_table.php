<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_sekolah') && !Schema::hasColumn('tb_sekolah', 'foto_qris')) {
            Schema::table('tb_sekolah', function (Blueprint $table) {
                $table->longText('foto_qris')->nullable()->after('website');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tb_sekolah') && Schema::hasColumn('tb_sekolah', 'foto_qris')) {
            Schema::table('tb_sekolah', function (Blueprint $table) {
                $table->dropColumn('foto_qris');
            });
        }
    }
};
