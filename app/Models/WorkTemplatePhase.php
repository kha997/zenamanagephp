<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTemplatePhase extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_template_phases';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_version_id',
        'phase_key',
        'name',
        'description',
        'phase_order',
        'default_offset_days',
        'config_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'phase_order' => 'integer',
        'default_offset_days' => 'integer',
        'config_json' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateVersion::class, 'work_template_version_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkTemplateTask::class, 'work_template_phase_id')->orderBy('task_order');
    }
}
