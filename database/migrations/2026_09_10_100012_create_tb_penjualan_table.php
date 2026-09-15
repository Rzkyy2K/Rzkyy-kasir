<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_penjualan` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_penjualan')) {
            return;
        }

        Schema::create('tb_penjualan', function (Blueprint $table) {
            $table->integer('id_penjualan', true);
            $table->primary('id_penjualan');
            $table->integer('id_sekolah')->nullable();
            $table->integer('id_user')->nullable();
            $table->integer('id_pelanggan')->nullable();
            $table->dateTime('tanggal_penjualan')->nullable();
            $table->decimal('total_faktur', 14, 2)->nullable();
            $table->decimal('total_bayar', 14, 2)->nullable();
            $table->decimal('kembalian', 14, 2)->nullable();
            $table->string('status_pembayaran', 30)->nullable();
            $table->enum('jenis_transaksi', ['tunai', 'kredit'])->nullable();
            $table->string('cara_bayar', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->boolean('is_delete')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah');
            $table->foreign('id_user')
                ->references('id_user')
                ->on('tb_user');
            $table->foreign('id_pelanggan')
                ->references('id_pelanggan')
                ->on('tb_pelanggan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_penjualan');
    }
};
