<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UserTenant Pivot Model
 * 
 * Represents the many-to-many relationship between users and tenants
 * with additional metadata (role, is_default).
 */
class UserTenant extends Pivot
{
    use HasUlids, SoftDeletes;

    protected $table = 'user_tenants';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'role',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public $timestamps = true;
}
