<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TemplateDiscipline Model - Discipline within a Template Set
 * 
 * Represents a discipline (e.g., ARC, MEP, STR, LND) in a WBS template set.
 * 
 * @property string $id ULID primary key
 * @property string $set_id Template set ID
 * @property string $code Discipline code (e.g., "ARC")
 * @property string $name Discipline name
 * @property string|null $color_hex Color hex code for UI display
 * @property int $order_index Order index for sorting
 * @property array|null $metadata Additional metadata (JSON)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TemplateDiscipline extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'template_disciplines';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'set_id',
        'code',
        'name',
        'color_hex',
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
     * Get the template set this discipline belongs to
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(TemplateSet::class, 'set_id');
    }

    /**
     * Get the tasks for this discipline
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(TemplateTask::class, 'discipline_id')->orderBy('order_index');
    }
}

