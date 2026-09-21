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
            if (!Schema::hasColumn('tb_penjualan', 'status_void')) {
                $table->string('status_void', 20)->default('none')->after('status_pembayaran');
            }
            if (!Schema::hasColumn('tb_penjualan', 'alasan_void')) {
                $table->text('alasan_void')->nullable()->after('status_void');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_requested_by')) {
                $table->integer('void_requested_by')->nullable()->after('alasan_void');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_requested_at')) {
                $table->dateTime('void_requested_at')->nullable()->after('void_requested_by');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_approved_by')) {
                $table->integer('void_approved_by')->nullable()->after('void_requested_at');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_approved_at')) {
                $table->dateTime('void_approved_at')->nullable()->after('void_approved_by');
            }
            if (!Schema::hasColumn('tb_penjualan', 'void_reject_reason')) {
                $table->text('void_reject_reason')->nullable()->after('void_approved_at');
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
                'status_void',
                'alasan_void',
                'void_requested_by',
                'void_requested_at',
                'void_approved_by',
                'void_approved_at',
                'void_reject_reason',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('tb_penjualan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
