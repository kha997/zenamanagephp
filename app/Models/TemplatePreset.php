<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TemplatePreset Model - Preset filter configuration for Template Sets
 * 
 * Represents a preset configuration that filters which phases, disciplines, and tasks
 * should be included when applying a template set.
 * 
 * @property string $id ULID primary key
 * @property string $set_id Template set ID
 * @property string $code Preset code (e.g., "HOUSE", "HIGH_RISE")
 * @property string $name Preset name
 * @property string|null $description Preset description
 * @property array $filters Filter configuration (JSON)
 *   Example: {
 *     "phases": ["CONCEPT"],
 *     "disciplines": ["ARC", "MEP"],
 *     "tasks": ["ARC-C01"],
 *     "include": [],
 *     "exclude": ["LND-PANO"]
 *   }
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TemplatePreset extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'template_presets';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'set_id',
        'code',
        'name',
        'description',
        'filters',
    ];

    protected $casts = [
        'filters' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the template set this preset belongs to
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(TemplateSet::class, 'set_id');
    }

    /**
     * Check if this preset matches the given selection
     * 
     * @param array $selection Selection array with phases, disciplines, tasks
     * @return bool
     */
    public function matches(array $selection): bool
    {
        $filters = $this->filters ?? [];

        // Check phases
        if (isset($filters['phases']) && !empty($filters['phases'])) {
            $selectedPhases = $selection['phases'] ?? [];
            if (empty(array_intersect($filters['phases'], $selectedPhases))) {
                return false;
            }
        }

        // Check disciplines
        if (isset($filters['disciplines']) && !empty($filters['disciplines'])) {
            $selectedDisciplines = $selection['disciplines'] ?? [];
            if (empty(array_intersect($filters['disciplines'], $selectedDisciplines))) {
                return false;
            }
        }

        // Check tasks
        if (isset($filters['tasks']) && !empty($filters['tasks'])) {
            $selectedTasks = $selection['tasks'] ?? [];
            if (empty(array_intersect($filters['tasks'], $selectedTasks))) {
                return false;
            }
        }

        return true;
    }
}

