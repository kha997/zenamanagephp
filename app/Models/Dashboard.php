<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Database\Factories\DashboardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Dashboard - lightweight dashboard record used by API tests.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $user_id
 * @property string $name
 * @property string|null $description
 * @property array|null $layout
 * @property array|null $widgets
 * @property array|null $preferences
 * @property bool $is_public
 * @property bool $is_active
 */
class Dashboard extends Model
{
    use HasUlids, HasFactory, TenantScope, SoftDeletes;

    protected $table = 'dashboards';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'description',
        'layout',
        'preferences',
        'is_public',
        'is_active',
        'slug',
    ];

    protected $casts = [
        'layout' => 'array',
        'preferences' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class, 'dashboard_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Dashboard $dashboard) {
            if ($dashboard->isForceDeleting()) {
                return;
            }

            $dashboard->widgets()->forceDelete();
        });
    }

    protected static function newFactory(): Factory
    {
        return DashboardFactory::new();
    }
}
