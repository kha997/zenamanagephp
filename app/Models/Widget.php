<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Widget - Represents a dashboard widget instance.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $user_id
 * @property string $dashboard_id
 * @property string $name
 * @property string|null $description
 * @property string|null $type
 * @property array|null $config
 * @property array|null $position
 * @property bool $is_active
 */
class Widget extends Model
{
    use HasUlids, HasFactory, TenantScope, SoftDeletes;

    protected $table = 'widgets';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'dashboard_id',
        'name',
        'description',
        'type',
        'config',
        'position',
        'is_active',
        'metadata'
    ];

    protected $casts = [
        'config' => 'array',
        'position' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array'
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): Factory
    {
        return WidgetFactory::new();
    }
}
