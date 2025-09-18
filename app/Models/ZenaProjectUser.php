<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class ZenaProjectUser extends Pivot
{
    use HasUlids;

    protected $table = 'zena_project_users';

    protected $fillable = [
        'project_id',
        'user_id',
        'role_on_project',
    ];

    public $timestamps = true;
}
