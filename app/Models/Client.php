<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;

/**
 * Client Model
 * 
 * Represents clients in the CRM system with lifecycle management
 * 
 * Relationships:
 * - hasMany(Quote) - Client can have multiple quotes
 * - hasMany(Project) - Client can have multiple projects
 */
class Client extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'company',
        'lifecycle_stage',
        'notes',
        'address',
        'custom_fields',
    ];

    protected $casts = [
        'address' => 'array',
        'custom_fields' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Lifecycle stages
     */
    public const LIFECYCLE_STAGES = [
        'lead' => 'Lead',
        'prospect' => 'Prospect', 
        'customer' => 'Customer',
        'inactive' => 'Inactive',
    ];

    /**
     * Get the quotes for the client
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Get the projects for the client
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get active quotes (not expired or rejected)
     */
    public function activeQuotes(): HasMany
    {
        return $this->quotes()->whereNotIn('status', ['expired', 'rejected']);
    }

    /**
     * Get accepted quotes
     */
    public function acceptedQuotes(): HasMany
    {
        return $this->quotes()->where('status', 'accepted');
    }

    /**
     * Get the latest quote for the client
     */
    public function latestQuote()
    {
        return $this->quotes()->latest()->first();
    }

    /**
     * Get the total value of accepted quotes
     */
    public function getTotalAcceptedValueAttribute(): float
    {
        return $this->acceptedQuotes()->sum('final_amount');
    }

    /**
     * Get the count of quotes by status
     */
    public function getQuoteCountByStatus(): array
    {
        return $this->quotes()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Check if client has active quotes
     */
    public function hasActiveQuotes(): bool
    {
        return $this->activeQuotes()->exists();
    }

    /**
     * Check if client is a customer (has accepted quotes or projects)
     */
    public function isCustomer(): bool
    {
        return $this->lifecycle_stage === 'customer' || 
               $this->acceptedQuotes()->exists() || 
               $this->projects()->exists();
    }

    /**
     * Update lifecycle stage based on quote activity
     */
    public function updateLifecycleStage(): void
    {
        $quoteCounts = $this->getQuoteCountByStatus();
        
        if ($this->isCustomer()) {
            $this->lifecycle_stage = 'customer';
        } elseif (isset($quoteCounts['sent']) && $quoteCounts['sent'] > 0) {
            $this->lifecycle_stage = 'prospect';
        } elseif (isset($quoteCounts['rejected']) && $quoteCounts['rejected'] >= 3) {
            $this->lifecycle_stage = 'inactive';
        } else {
            $this->lifecycle_stage = 'lead';
        }
        
        $this->save();
    }

    /**
     * Get formatted address
     */
    public function getFormattedAddressAttribute(): string
    {
        if (!$this->address) {
            return '';
        }

        $address = $this->address;
        $parts = array_filter([
            $address['street'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['postal_code'] ?? '',
            $address['country'] ?? '',
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get display name (company + name or just name)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->company) {
            return "{$this->company} - {$this->name}";
        }
        
        return $this->name;
    }

    /**
     * Scope for filtering by lifecycle stage
     */
    public function scopeByLifecycleStage($query, string $stage)
    {
        return $query->where('lifecycle_stage', $stage);
    }

    /**
     * Scope for active clients (not inactive)
     */
    public function scopeActive($query)
    {
        return $query->where('lifecycle_stage', '!=', 'inactive');
    }

    /**
     * Scope for customers
     */
    public function scopeCustomers($query)
    {
        return $query->where('lifecycle_stage', 'customer');
    }

    /**
     * Scope for prospects
     */
    public function scopeProspects($query)
    {
        return $query->where('lifecycle_stage', 'prospect');
    }

    /**
     * Scope for leads
     */
    public function scopeLeads($query)
    {
        return $query->where('lifecycle_stage', 'lead');
    }

    /**
     * Scope for inactive clients
     */
    public function scopeInactive($query)
    {
        return $query->where('lifecycle_stage', 'inactive');
    }

    /**
     * Scope for searching clients
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('company', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
