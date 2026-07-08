<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEndpoint extends Model
{
    use HasUlids;

    protected $table = 'webhook_endpoints';

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'event_keys',
        'is_active',
        'created_by',
        'last_delivered_at',
        'failure_count',
    ];

    protected $casts = [
        'event_keys' => 'array',
        'is_active' => 'boolean',
        'last_delivered_at' => 'datetime',
        'failure_count' => 'integer',
    ];

    protected $hidden = [
        'secret',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function matchesEventKey(string $eventKey): bool
    {
        foreach ((array) $this->event_keys as $prefix) {
            if ($prefix === '*' || str_starts_with($eventKey, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }
}
