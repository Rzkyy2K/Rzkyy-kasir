<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelanggan extends Model
{
    protected $table = 'tb_pelanggan';

    protected $primaryKey = 'id_pelanggan';

    public $timestamps = false;

    protected $guarded = ['id_pelanggan'];

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(KelompokPelanggan::class, 'id_kelompok_pelanggan', 'id_kelompok_pelanggan');
    }

    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class, 'id_pelanggan', 'id_pelanggan');
    }
}
