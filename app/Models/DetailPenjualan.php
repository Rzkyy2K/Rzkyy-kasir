<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPenjualan extends Model
{
    protected $table = 'tb_detail_penjualan';

    protected $primaryKey = 'id_detail_penjualan';

    public $timestamps = false;

    protected $guarded = ['id_detail_penjualan'];

    protected function casts(): array
    {
        return [
            'jumlah_barang' => 'integer',
            'harga_beli' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'diskon_nilai' => 'decimal:2',
            'diskon_nominal' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
