<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'currency',
        'features',
        'max_users',
        'max_projects',
        'storage_limit_mb',
        'is_active',
        'sort_order',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'features' => 'array',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_active' => 'boolean',
        'max_users' => 'integer',
        'max_projects' => 'integer',
        'storage_limit_mb' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get all subscriptions for this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    /**
     * Get active subscriptions for this plan
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id')
            ->where('status', 'active');
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered plans
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('monthly_price');
    }

    /**
     * Get yearly price with discount calculation
     */
    public function getYearlyPriceWithDiscountAttribute(): float
    {
        if (!$this->yearly_price) {
            return $this->monthly_price * 12;
        }
        
        return $this->yearly_price;
    }

    /**
     * Get discount percentage for yearly billing
     */
    public function getYearlyDiscountPercentageAttribute(): float
    {
        if (!$this->yearly_price) {
            return 0;
        }
        
        $monthlyTotal = $this->monthly_price * 12;
        return round((($monthlyTotal - $this->yearly_price) / $monthlyTotal) * 100, 1);
    }
}