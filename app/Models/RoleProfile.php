<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

/**
 * RoleProfile Model
 * 
 * Round 244: Role Access Profiles
 * 
 * Represents a role profile (template) that contains a collection of roles.
 * Profiles can be assigned to users to quickly grant them multiple roles at once.
 * 
 * @property string $id ULID primary key
 * @property string $name Profile name
 * @property string|null $description Profile description
 * @property array $roles Array of role IDs or slugs
 * @property bool $is_active Whether the profile is active
 * @property string $tenant_id Tenant ID (ULID)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RoleProfile extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'role_profiles';

    /**
     * Key type for ULID
     */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'roles',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'roles' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: Filter by tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Only active profiles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get Role models from the profile's role IDs/slugs
     * 
     * @return Collection<Role>
     */
    public function getRoleModels(): Collection
    {
        $roleIdentifiers = $this->roles ?? [];
        
        if (empty($roleIdentifiers)) {
            return new Collection();
        }

        $roleIds = [];
        
        foreach ($roleIdentifiers as $identifier) {
            // Try to find by ID first
            $role = Role::find($identifier);
            
            // If not found by ID, try by name
            if (!$role) {
                $role = Role::where('name', $identifier)
                    ->where(function($query) {
                        $query->where('tenant_id', $this->tenant_id)
                              ->orWhereNull('tenant_id'); // System roles
                    })
                    ->first();
            }
            
            // If still not found, try by slug (if roles have slug field)
            if (!$role) {
                $role = Role::where(function($query) {
                    $query->where('tenant_id', $this->tenant_id)
                          ->orWhereNull('tenant_id'); // System roles
                })
                    ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
                    ->first();
            }
            
            if ($role) {
                $roleIds[] = $role->id;
            }
        }
        
        // Return Eloquent Collection by querying all at once
        if (empty($roleIds)) {
            return new Collection();
        }
        
        return Role::whereIn('id', $roleIds)->get();
    }
}
