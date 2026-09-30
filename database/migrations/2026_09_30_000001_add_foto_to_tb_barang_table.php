<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_barang') && !Schema::hasColumn('tb_barang', 'foto')) {
            Schema::table('tb_barang', function (Blueprint $table) {
                $table->mediumText('foto')->nullable()->after('satuan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tb_barang') && Schema::hasColumn('tb_barang', 'foto')) {
            Schema::table('tb_barang', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }
    }
};
