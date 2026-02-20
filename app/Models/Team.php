<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string|null $team_lead_id
 * @property string|null $department
 * @property string $status
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Team extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'teams';
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'team_lead_id',
        'leader_id',
        'department',
        'status',
        'is_active',
        'settings',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status' => 'string',
        'settings' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
        'settings' => '[]',
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * Team status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

    public const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Team member roles
     */
    public const ROLE_MEMBER = 'member';
    public const ROLE_LEAD = 'lead';
    public const ROLE_ADMIN = 'admin';

    public const VALID_ROLES = [
        self::ROLE_MEMBER,
        self::ROLE_LEAD,
        self::ROLE_ADMIN,
    ];

    /**
     * RELATIONSHIPS
     */

    /**
     * Get the tenant that owns the team.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the team lead.
     */
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function getLeaderIdAttribute(): ?string
    {
        return $this->team_lead_id;
    }

    public function setLeaderIdAttribute(?string $value): void
    {
        $this->attributes['team_lead_id'] = $value;
    }

    /**
     * Get the user who created the team.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the team.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the team members.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
                    ->withPivot(['role', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Get active team members only.
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivotNull('left_at');
    }

    /**
     * Get team leads and admins.
     */
    public function leaders(): BelongsToMany
    {
        return $this->members()->wherePivotIn('role', [self::ROLE_LEAD, self::ROLE_ADMIN]);
    }

    /**
     * Get tasks assigned to this team.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get task assignments for this team.
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get projects where this team is involved.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_teams', 'team_id', 'project_id')
                    ->withPivot(['role', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * SCOPES
     */

    /**
     * Scope: Filter by tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by department
     */
    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope: Search teams
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * BUSINESS LOGIC METHODS
     */

    /**
     * Add a member to the team.
     */
    public function addMember(string $userId, string $role = self::ROLE_MEMBER): void
    {
        if ($this->members()->where('user_id', $userId)->exists()) {
            throw new \InvalidArgumentException('User is already a team member');
        }

        $this->members()->attach($userId, [
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(string $userId): void
    {
        $this->members()->updateExistingPivot($userId, [
            'left_at' => now(),
        ]);
    }

    /**
     * Update member role.
     */
    public function updateMemberRole(string $userId, string $role): void
    {
        if (!in_array($role, self::VALID_ROLES)) {
            throw new \InvalidArgumentException('Invalid role');
        }

        $this->members()->updateExistingPivot($userId, [
            'role' => $role,
        ]);
    }

    /**
     * Check if user is a team member.
     */
    public function hasMember(string $userId): bool
    {
        return $this->activeMembers()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user is a team leader.
     */
    public function hasLeader(string $userId): bool
    {
        return $this->leaders()->where('user_id', $userId)->exists();
    }

    /**
     * Get team size.
     */
    public function getSizeAttribute(): int
    {
        return $this->activeMembers()->count();
    }

    /**
     * Get team statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_members' => $this->activeMembers()->count(),
            'leaders' => $this->leaders()->count(),
            'members' => $this->activeMembers()->wherePivot('role', self::ROLE_MEMBER)->count(),
            'active_tasks' => $this->taskAssignments()->whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed_tasks' => $this->taskAssignments()->where('status', 'completed')->count(),
            'projects_count' => $this->projects()->count(),
        ];
    }

    /**
     * Check if team can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->tasks()->count() === 0 && $this->projects()->count() === 0;
    }

    /**
     * Archive the team.
     */
    public function archive(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Restore the team.
     */
    public function restore(): void
    {
        $this->update(['is_active' => true]);
    }
}
