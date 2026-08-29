<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EnforcesServiceLineIntegrity;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP-046 — Opportunity-side canonical Service-Line membership row
 * (Gate 2 §3 Option B).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $opportunity_id
 * @property string $service_line
 * @property string $provenance
 * @property string|null $source
 * @property string|null $created_by
 */
class OpportunityServiceLine extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesServiceLineIntegrity;

    protected $table = 'opportunity_service_lines';

    /**
     * tenant_id is deliberately excluded — it can only be set by
     * EnforcesServiceLineIntegrity's creating hook, derived from the
     * parent Opportunity (Gate 2 §5).
     */
    protected $fillable = [
        'opportunity_id',
        'service_line',
        'provenance',
        'source',
        'created_by',
    ];

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    protected function resolveParentTenantId(): ?string
    {
        $opportunity = Opportunity::query()
            ->withoutGlobalScope('tenant')
            ->find($this->opportunity_id);

        return $opportunity?->tenant_id;
    }
}
