<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EnforcesServiceLineIntegrity;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP-046 — Project-side canonical Service-Line membership row
 * (Gate 2 §3 Option B).
 *
 * Receives zero rows from any GAP-046 backfill mechanism (Gate 2 §7,
 * decided Option A — no historical Project backfill).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $service_line
 * @property string $provenance
 * @property string|null $source
 * @property string|null $created_by
 */
class ProjectServiceLine extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesServiceLineIntegrity;

    protected $table = 'project_service_lines';

    /**
     * tenant_id is deliberately excluded — it can only be set by
     * EnforcesServiceLineIntegrity's creating hook, derived from the
     * parent Project (Gate 2 §5).
     */
    protected $fillable = [
        'project_id',
        'service_line',
        'provenance',
        'source',
        'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    protected function resolveParentTenantId(): ?string
    {
        $project = Project::query()
            ->withoutGlobalScope('tenant')
            ->find($this->project_id);

        return $project?->tenant_id;
    }
}
