<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;

/**
 * Quote Model
 * 
 * Represents quotes in the quoting system
 * 
 * Relationships:
 * - belongsTo(Client) - Quote belongs to a client
 * - belongsTo(Project) - Quote can be linked to a project (nullable)
 */
class Quote extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'project_id',
        'type',
        'status',
        'title',
        'description',
        'total_amount',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'final_amount',
        'line_items',
        'terms_conditions',
        'valid_until',
        'sent_at',
        'viewed_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'line_items' => 'array',
        'terms_conditions' => 'array',
        'total_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Quote types
     */
    public const TYPES = [
        'design' => 'Design',
        'construction' => 'Construction',
    ];

    /**
     * Quote statuses
     */
    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'viewed' => 'Viewed',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ];

    /**
     * Get the client that owns the quote
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the project associated with the quote
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created the quote
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the quote
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get documents associated with this quote
     */
    public function documents()
    {
        return $this->hasMany(Document::class, 'quote_id');
    }

    /**
     * Check if quote is expired
     */
    public function isExpired(): bool
    {
        return $this->valid_until < now()->toDateString();
    }

    /**
     * Check if quote is active (not expired, rejected, or accepted)
     */
    public function isActive(): bool
    {
        return !$this->isExpired() && 
               !in_array($this->status, ['rejected', 'accepted']);
    }

    /**
     * Check if quote can be sent
     */
    public function canBeSent(): bool
    {
        return $this->status === 'draft' && !$this->isExpired();
    }

    /**
     * Check if quote can be accepted
     */
    public function canBeAccepted(): bool
    {
        return in_array($this->status, ['sent', 'viewed']) && !$this->isExpired();
    }

    /**
     * Check if quote can be rejected
     */
    public function canBeRejected(): bool
    {
        return in_array($this->status, ['sent', 'viewed']) && !$this->isExpired();
    }

    /**
     * Mark quote as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark quote as viewed
     */
    public function markAsViewed(): void
    {
        if ($this->status === 'sent') {
            $this->update([
                'status' => 'viewed',
                'viewed_at' => now(),
            ]);
        }
    }

    /**
     * Mark quote as accepted
     */
    public function markAsAccepted(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        // Update client lifecycle stage
        $this->client->updateLifecycleStage();
    }

    /**
     * Mark quote as rejected
     */
    public function markAsRejected(string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Update client lifecycle stage
        $this->client->updateLifecycleStage();
    }

    /**
     * Mark quote as expired
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }

    /**
     * Calculate final amount including tax and discount
     */
    public function calculateFinalAmount(): float
    {
        $subtotal = $this->total_amount;
        $discount = $this->discount_amount;
        $taxableAmount = $subtotal - $discount;
        $tax = $taxableAmount * ($this->tax_rate / 100);
        
        return $taxableAmount + $tax;
    }

    /**
     * Update calculated amounts
     */
    public function updateCalculatedAmounts(): void
    {
        $this->tax_amount = ($this->total_amount - $this->discount_amount) * ($this->tax_rate / 100);
        $this->final_amount = $this->calculateFinalAmount();
        $this->save();
    }

    /**
     * Get formatted line items
     */
    public function getFormattedLineItemsAttribute(): array
    {
        if (!$this->line_items) {
            return [];
        }

        return array_map(function ($item) {
            return [
                'description' => $item['description'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'total' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
            ];
        }, $this->line_items);
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'viewed' => 'yellow',
            'accepted' => 'green',
            'rejected' => 'red',
            'expired' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get type badge color
     */
    public function getTypeBadgeColorAttribute(): string
    {
        return match($this->type) {
            'design' => 'purple',
            'construction' => 'orange',
            default => 'gray',
        };
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for active quotes
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['expired', 'rejected', 'accepted']);
    }

    /**
     * Scope for expired quotes
     */
    public function scopeExpired($query)
    {
        return $query->where('valid_until', '<', now()->toDateString())
                    ->whereNotIn('status', ['expired', 'rejected', 'accepted']);
    }

    /**
     * Scope for quotes expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('valid_until', '<=', now()->addDays($days)->toDateString())
                    ->where('valid_until', '>', now()->toDateString())
                    ->whereNotIn('status', ['expired', 'rejected', 'accepted']);
    }

    /**
     * Scope for searching quotes
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhereHas('client', function ($clientQuery) use ($search) {
                  $clientQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('company', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Scope for quotes by client
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope for quotes by project
     */
    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope for quotes without project
     */
    public function scopeWithoutProject($query)
    {
        return $query->whereNull('project_id');
    }
}
