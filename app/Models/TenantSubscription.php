<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'amount',
        'currency',
        'started_at',
        'renew_at',
        'canceled_at',
        'expires_at',
        'stripe_subscription_id',
        'stripe_customer_id',
        'metadata',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'amount' => 'decimal:2',
        'started_at' => 'datetime',
        'renew_at' => 'datetime',
        'canceled_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the subscription
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the plan for this subscription
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'plan_id');
    }

    /**
     * Get all invoices for this subscription
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'subscription_id');
    }

    /**
     * Get all payments for this subscription
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'subscription_id');
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for canceled subscriptions
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    /**
     * Scope for trial subscriptions
     */
    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    /**
     * Scope for subscriptions by plan
     */
    public function scopeByPlan($query, $planSlug)
    {
        return $query->whereHas('plan', function ($q) use ($planSlug) {
            $q->where('slug', $planSlug);
        });
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               (!$this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Check if subscription is in trial
     */
    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    /**
     * Check if subscription is canceled
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Get days until renewal
     */
    public function getDaysUntilRenewalAttribute(): int
    {
        if (!$this->renew_at) {
            return 0;
        }
        
        return max(0, Carbon::now()->diffInDays($this->renew_at, false));
    }

    /**
     * Get monthly recurring revenue for this subscription
     */
    public function getMonthlyRevenueAttribute(): float
    {
        if ($this->billing_cycle === 'yearly') {
            return $this->amount / 12;
        }
        
        return $this->amount;
    }

    /**
     * Get annual recurring revenue for this subscription
     */
    public function getAnnualRevenueAttribute(): float
    {
        if ($this->billing_cycle === 'monthly') {
            return $this->amount * 12;
        }
        
        return $this->amount;
    }
}