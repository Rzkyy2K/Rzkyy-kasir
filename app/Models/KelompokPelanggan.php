<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelompokPelanggan extends Model
{
    protected $table = 'tb_kelompok_pelanggan';

    protected $primaryKey = 'id_kelompok_pelanggan';

    public $timestamps = false;

    protected $guarded = ['id_kelompok_pelanggan'];

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class, 'id_kelompok_pelanggan', 'id_kelompok_pelanggan');
    }
}
