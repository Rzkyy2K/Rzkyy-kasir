<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin persis tabel `tb_pembelian` pada database db_rizky.
 * Dilewati otomatis bila tabel sudah ada (database warisan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_pembelian')) {
            return;
        }

        Schema::create('tb_pembelian', function (Blueprint $table) {
            $table->integer('id_pembelian', true);
            $table->integer('id_sekolah')->nullable();
            $table->integer('id_supplier')->nullable();
            $table->integer('id_user')->nullable();
            $table->string('nomor_faktur', 50)->nullable();
            $table->dateTime('tanggal_faktur')->nullable();
            $table->decimal('total_bayar', 14, 2)->nullable();
            $table->string('status_pembelian', 20)->nullable();
            $table->enum('jenis_transaksi', ['tunai', 'kredit'])->nullable();
            $table->string('cara_bayar', 11)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->boolean('is_delete')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah');
            $table->foreign('id_supplier')
                ->references('id_supplier')
                ->on('tb_supplier');
            $table->foreign('id_user')
                ->references('id_user')
                ->on('tb_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_pembelian');
    }
};
