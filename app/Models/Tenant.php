<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tenant Model - Represents an organization/company in the multi-tenant system
 * 
 * @property string $id ULID primary key
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string|null $database_name
 * @property array|null $settings
 * @property string $status
 * @property bool $is_active
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Tenant extends Model
{
    use HasUlids, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'database_name',
        'settings',
        'status',
        'is_active',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'status' => 'trial',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Tenant $tenant) => static::ensureSlug($tenant));

        static::updating(function (Tenant $tenant): void {
            if ($tenant->isDirty('name') && empty($tenant->slug)) {
                static::ensureSlug($tenant);
            }
        });
    }

    /**
     * Ensure that the tenant has a valid, unique slug before saving.
     */
    protected static function ensureSlug(Tenant $tenant): void
    {
        if (!empty($tenant->slug)) {
            return;
        }

        $baseSlug = static::generateBaseSlug($tenant);
        $slug = $baseSlug;
        $counter = 2;

        while (static::slugExists($slug, $tenant)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $tenant->slug = $slug;
    }

    /**
     * Build a slug base from tenant name or fallback to a tenant identifier.
     */
    protected static function generateBaseSlug(Tenant $tenant): string
    {
        $slug = Str::slug((string) $tenant->name);

        if ($slug === '') {
            $fallback = $tenant->id ?? Str::ulid();
            $slug = "tenant-{$fallback}";
        }

        return $slug;
    }

    protected static function slugExists(string $slug, Tenant $tenant): bool
    {
        $query = static::where('slug', $slug);

        if ($tenant->exists && $tenant->id) {
            $query->where('id', '!=', $tenant->id);
        }

        return $query->exists();
    }

    /**
     * Get all users belonging to this tenant
     *
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all projects belonging to this tenant
     *
     * @return HasMany
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Check if tenant is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenant trial has expired
     *
     * @return bool
     */
    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}
