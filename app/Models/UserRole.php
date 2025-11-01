<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserRole extends Pivot
{

    protected $table = 'user_roles';

    protected $fillable = [
        'user_id',
        'role_id',
    ];

    public $timestamps = true;
}