<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ZenaRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'scope',
        'is_active',
        'tenant_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the permissions for the role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(ZenaPermission::class, 'role_permissions');
    }

    /**
     * Get the users that have this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
