<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkInstance extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_instances';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'work_template_version_id',
        'status',
        'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateVersion::class, 'work_template_version_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkInstanceStep::class)->orderBy('step_order');
    }
}
