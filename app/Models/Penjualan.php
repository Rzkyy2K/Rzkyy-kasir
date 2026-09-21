<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penjualan extends Model
{
    protected $table = 'tb_penjualan';

    protected $primaryKey = 'id_penjualan';

    public $timestamps = false;

    protected $guarded = ['id_penjualan'];

    protected function casts(): array
    {
        return [
            'tanggal_penjualan' => 'datetime',
            'void_requested_at' => 'datetime',
            'void_admin_verified_at' => 'datetime',
            'void_approved_at' => 'datetime',
            'total_faktur' => 'decimal:2',
            'total_bayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(PosUser::class, 'id_user', 'id_user');
    }

    public function voidRequester(): BelongsTo
    {
        return $this->belongsTo(PosUser::class, 'void_requested_by', 'id_user');
    }

    public function adminVerifier(): BelongsTo
    {
        return $this->belongsTo(PosUser::class, 'void_admin_verified_by', 'id_user');
    }

    public function voidApprover(): BelongsTo
    {
        return $this->belongsTo(PosUser::class, 'void_approved_by', 'id_user');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'id_penjualan', 'id_penjualan');
    }
}
