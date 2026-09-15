<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sekolah extends Model
{
    protected $table = 'tb_sekolah';

    protected $primaryKey = 'id_sekolah';

    public $timestamps = false;

    protected $guarded = ['id_sekolah'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'id_sekolah', 'id_sekolah');
    }

    public function users(): HasMany
    {
        return $this->hasMany(PosUser::class, 'id_sekolah', 'id_sekolah');
    }
}
