<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tb_penjualan', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_penjualan', 'void_telepon_kasir')) {
                $table->string('void_telepon_kasir', 30)->nullable()->after('alasan_void');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_admin_verified_by')) {
                $table->integer('void_admin_verified_by')->nullable()->after('void_requested_at');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_admin_verified_at')) {
                $table->dateTime('void_admin_verified_at')->nullable()->after('void_admin_verified_by');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_admin_notes')) {
                $table->text('void_admin_notes')->nullable()->after('void_admin_verified_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_penjualan', function (Blueprint $table) {
            $cols = [
                'void_telepon_kasir',
                'void_admin_verified_by',
                'void_admin_verified_at',
                'void_admin_notes',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('tb_penjualan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
