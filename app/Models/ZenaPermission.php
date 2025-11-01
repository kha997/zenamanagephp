<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ZenaPermission extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'name',
        'description'
    ];

    /**
     * Relationship: Permission belongs to many roles
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ZenaRole::class, 'zena_role_permissions', 'permission_id', 'role_id');
    }
}