<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ZenaPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'module',
        'action',
        'is_active',
        'tenant_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ZenaRole::class, 'role_permissions');
    }
}
