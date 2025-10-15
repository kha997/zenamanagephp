<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class BillingInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'tenant_id',
        'subscription_id',
        'invoice_number',
        'description',
        'amount',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'issue_date',
        'due_date',
        'paid_at',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'line_items',
        'metadata',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'line_items' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the invoice
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the subscription for this invoice
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    /**
     * Get all payments for this invoice
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'invoice_id');
    }

    /**
     * Scope for paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for unpaid invoices
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    /**
     * Scope for overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->where('due_date', '<', Carbon::now());
    }

    /**
     * Scope for invoices by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('issue_date', [$startDate, $endDate]);
    }

    /**
     * Scope for invoices by tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || 
               ($this->status === 'unpaid' && $this->due_date->isPast());
    }

    /**
     * Check if invoice is unpaid
     */
    public function isUnpaid(): bool
    {
        return in_array($this->status, ['unpaid', 'overdue']);
    }

    /**
     * Get days overdue
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return max(0, Carbon::now()->diffInDays($this->due_date));
    }

    /**
     * Get formatted invoice number
     */
    public function getFormattedInvoiceNumberAttribute(): string
    {
        return 'INV-' . str_pad($this->invoice_number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get total amount with currency
     */
    public function getFormattedTotalAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->total_amount, 2);
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid($paidAt = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => $paidAt ?? Carbon::now(),
        ]);
    }

    /**
     * Mark invoice as overdue
     */
    public function markAsOverdue(): void
    {
        if ($this->status === 'unpaid' && $this->due_date->isPast()) {
            $this->update(['status' => 'overdue']);
        }
    }
}