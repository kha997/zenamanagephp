<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZenaRole extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'name',
        'scope',
        'description',
        'permissions',
        'is_active'
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Relationship: Role has many users
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }

    /**
     * Relationship: Role has many permissions
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(ZenaPermission::class, 'zena_role_permissions', 'role_id', 'permission_id');
    }

    /**
     * Scope: Active roles only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: System roles only
     */
    public function scopeSystem($query)
    {
        return $query->where('scope', 'system');
    }
}