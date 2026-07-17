<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTemplateVersion extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_template_versions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_id',
        'semver',
        'schema_version',
        'content_json',
        'is_immutable',
        'published_at',
        'published_by',
        'source_version_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'schema_version' => 'integer',
        'content_json' => 'array',
        'is_immutable' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkTemplate::class, 'work_template_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkTemplateStep::class)->orderBy('step_order');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(WorkTemplatePhase::class, 'work_template_version_id')->orderBy('phase_order');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_version_id');
    }
}
