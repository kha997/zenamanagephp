<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opportunity — trung tâm pipeline sale (spec crm-zena: 14 stage).
 *
 * @property-read Account|null $account
 */
class Opportunity extends Model
{
    use HasUlids;
    use TenantScope;

    public const STAGE_NEW_LEAD = 'new_lead';
    public const STAGE_QUALIFIED = 'qualified';
    public const STAGE_CONTACTED = 'contacted';
    public const STAGE_BRIEF_DISCOVERY = 'brief_discovery';
    public const STAGE_SURVEY_OR_INPUTS_RECEIVED = 'survey_or_inputs_received';
    public const STAGE_SCOPE_DEFINED = 'scope_defined';
    public const STAGE_PROPOSAL_DRAFT = 'proposal_draft';
    public const STAGE_PROPOSAL_SENT = 'proposal_sent';
    public const STAGE_NEGOTIATION = 'negotiation';
    public const STAGE_CONTRACTING = 'contracting';
    public const STAGE_WON = 'won';
    public const STAGE_LOST = 'lost';
    public const STAGE_NURTURE = 'nurture';
    public const STAGE_NO_BID = 'no_bid';

    public const ACTIVE_STAGES = [
        self::STAGE_NEW_LEAD,
        self::STAGE_QUALIFIED,
        self::STAGE_CONTACTED,
        self::STAGE_BRIEF_DISCOVERY,
        self::STAGE_SURVEY_OR_INPUTS_RECEIVED,
        self::STAGE_SCOPE_DEFINED,
        self::STAGE_PROPOSAL_DRAFT,
        self::STAGE_PROPOSAL_SENT,
        self::STAGE_NEGOTIATION,
        self::STAGE_CONTRACTING,
    ];

    public const TERMINAL_STAGES = [
        self::STAGE_WON,
        self::STAGE_LOST,
        self::STAGE_NO_BID,
    ];

    public const VALID_STAGES = [
        self::STAGE_NEW_LEAD,
        self::STAGE_QUALIFIED,
        self::STAGE_CONTACTED,
        self::STAGE_BRIEF_DISCOVERY,
        self::STAGE_SURVEY_OR_INPUTS_RECEIVED,
        self::STAGE_SCOPE_DEFINED,
        self::STAGE_PROPOSAL_DRAFT,
        self::STAGE_PROPOSAL_SENT,
        self::STAGE_NEGOTIATION,
        self::STAGE_CONTRACTING,
        self::STAGE_WON,
        self::STAGE_LOST,
        self::STAGE_NURTURE,
        self::STAGE_NO_BID,
    ];

    public const VALID_SERVICE_CATEGORIES = [
        'architecture', 'interior', 'landscape', 'structure', 'mep',
        'construction', 'inspection', 'consulting', 'combined_package',
    ];

    public const VALID_FORECAST_CATEGORIES = [
        'pipeline', 'best_case', 'commit', 'closed_won', 'closed_lost', 'nurture',
    ];

    public const VALID_PRIORITIES = ['low', 'medium', 'high'];

    protected $table = 'opportunities';

    protected $fillable = [
        'tenant_id',
        'account_id',
        'opportunity_name',
        'service_category',
        'service_scope_summary',
        'pipeline_stage',
        'forecast_category',
        'estimated_fee',
        'estimated_project_value',
        'probability',
        'expected_close_date',
        'sales_owner_id',
        'technical_owner_id',
        'priority',
        'lost_reason',
        'converted_project_id',
        'created_by',
        'external_boq_project_code',
        'external_quote_id',
        'external_quote_snapshot',
        'external_quote_synced_at',
    ];

    protected $casts = [
        'estimated_fee' => 'decimal:0',
        'estimated_project_value' => 'decimal:0',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'external_quote_snapshot' => 'array',
        'external_quote_synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function salesOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_owner_id');
    }

    public function technicalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_owner_id');
    }

    public function convertedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'converted_project_id');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isTerminal(): bool
    {
        return in_array((string) $this->pipeline_stage, self::TERMINAL_STAGES, true);
    }
}
