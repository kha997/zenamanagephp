<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Account — khách hàng (cá nhân hoặc công ty). Port từ spec crm-zena.
 * Also implements Authenticatable so it can be used directly as the
 * identity for the `client` portal auth guard (Phase 6) — no password,
 * no remember-token columns needed; the trait's defaults handle this.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $display_name
 */
class Account extends Model implements AuthenticatableContract
{
    use HasUlids;
    use Authenticatable;
    use TenantScope;

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_COMPANY = 'company';
    public const VALID_TYPES = [self::TYPE_INDIVIDUAL, self::TYPE_COMPANY];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';
    public const VALID_STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ARCHIVED];

    protected $table = 'accounts';

    protected $fillable = [
        'tenant_id',
        'account_code',
        'account_type',
        'display_name',
        'legal_name',
        'tax_code',
        'phone',
        'email',
        'address',
        'province_or_city',
        'source_summary',
        'owner_id',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'account_id');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
