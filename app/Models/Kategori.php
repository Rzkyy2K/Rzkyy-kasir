<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'tb_kategori';

    protected $primaryKey = 'id_kategori';

    public $timestamps = false;

    protected $guarded = ['id_kategori'];

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(KelompokKategori::class, 'id_kelompok', 'id_kelompok');
    }

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'id_kategori', 'id_kategori');
    }
}
