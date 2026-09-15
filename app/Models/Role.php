<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'id_role';

    public $timestamps = false;

    protected $guarded = ['id_role'];

    public function users(): HasMany
    {
        return $this->hasMany(PosUser::class, 'id_role', 'id_role');
    }
}
