<?php declare(strict_types=1);

namespace Src\RBAC\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;

/**
 * Model RolePermission - Bảng trung gian cho Role và Permission
 * 
 * @property string $id
 * @property string $role_id
 * @property string $permission_id
 * @property bool $allow_override
 */
class RolePermission extends Pivot
{
    use HasUlids, HasTimestamps;

    protected $table = 'role_permissions';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'role_id',
        'permission_id', 
        'allow_override'
    ];

    protected $casts = [
        'allow_override' => 'boolean',
    ];
}