<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * TemplatePhase Model - Phase within a Template Set
 * 
 * Represents a phase (e.g., CONCEPT, DESIGN, CONSTRUCTION) in a WBS template set.
 * 
 * @property string $id ULID primary key
 * @property string $set_id Template set ID
 * @property string $code Phase code (e.g., "CONCEPT")
 * @property string $name Phase name
 * @property int $order_index Order index for sorting
 * @property array|null $metadata Additional metadata (JSON)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TemplatePhase extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'template_phases';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'set_id',
        'code',
        'name',
        'order_index',
        'metadata',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'order_index' => 0,
    ];

    /**
     * Get the template set this phase belongs to
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(TemplateSet::class, 'set_id');
    }

    /**
     * Get the tasks for this phase
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(TemplateTask::class, 'phase_id')->orderBy('order_index');
    }

    /**
     * Scope to order phases by order_index
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_index');
    }
}

