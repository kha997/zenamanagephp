<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class ZenaRolePermission extends Pivot
{
    use HasUlids;

    protected $table = 'zena_role_permissions';

    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    public $timestamps = true;
}
