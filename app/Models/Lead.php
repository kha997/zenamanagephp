<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lead Inbox — capture nhanh nhu cầu thô (từ Zalo/hotline/fanpage),
 * sau đó convert thành Opportunity. Port từ spec crm-zena (model LeadInbox).
 */
class Lead extends Model
{
    use HasUlids;

    public const STATUS_NEW = 'new';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_DISCARDED = 'discarded';

    public const VALID_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONVERTED,
        self::STATUS_DISCARDED,
    ];

    public const VALID_SOURCES = ['facebook', 'zalo', 'referral', 'website', 'walk_in', 'hotline', 'other'];

    protected $table = 'leads';

    protected $fillable = [
        'tenant_id',
        'contact_hint',
        'project_description',
        'source',
        'status',
        'converted_opportunity_id',
        'notes',
        'captured_by',
    ];

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function convertedOpportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'converted_opportunity_id');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
