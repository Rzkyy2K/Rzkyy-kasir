<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PosUser extends Authenticatable
{
    protected $connection = 'mysql';

    protected $table = 'tb_user';

    protected $primaryKey = 'id_user';

    public $timestamps = false;

    protected $guarded = ['id_user'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            // Otomatis hash setiap password yang disimpan (seperti model
            // User bawaan Laravel). Melewati nilai yang sudah hash.
            'password' => 'hashed',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role', 'id_role');
    }

    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class, 'id_user', 'id_user');
    }
}
