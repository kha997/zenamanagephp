<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class ZenaUserRole extends Pivot
{
    use HasUlids;

    protected $table = 'zena_user_roles';

    protected $fillable = [
        'user_id',
        'role_id',
    ];

    public $timestamps = true;
}
