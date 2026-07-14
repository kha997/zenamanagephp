<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string $context
 * @property string|null $created_by
 * @property string|null $updated_by
 * @method static \App\Models\DeliverableTemplate create(array<string, mixed> $attributes = [])
 */
class DeliverableTemplate extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'deliverable_templates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'status',
        'context',
        'created_by',
        'updated_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DeliverableTemplateVersion::class);
    }

    /**
     * @return HasOne<DeliverableTemplateVersion, $this>
     */
    public function latestPublishedVersion(): HasOne
    {
        /** @var HasOne<DeliverableTemplateVersion, $this> $relation */
        $relation = $this->hasOne(DeliverableTemplateVersion::class)
            ->whereNotNull('published_at');

        return $relation->latestOfMany('published_at');
    }

}
