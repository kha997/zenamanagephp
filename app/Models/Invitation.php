<?php declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string|null $team_id
 * @property string|null $token
 * @property string|null $token_hash
 * @property int|null $token_version
 * @property string $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $full_name
 * @property string $role
 * @property string|null $message
 * @property int $organization_id
 * @property int|null $project_id
 * @property int $invited_by
 * @property string|null $invited_by_user_id
 * @property string|null $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property string|null $accepted_by_user_id
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_by_user_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $notes
 */
class Invitation extends Model
{
    use HasFactory;

    public const TOKEN_VERSION_LEGACY = 1;
    public const TOKEN_VERSION_HASH_ONLY = 2;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'invitations';

    protected $fillable = [
        'tenant_id',
        'team_id',
        'token',
        'token_hash',
        'token_version',
        'email',
        'first_name',
        'last_name',
        'role',
        'message',
        'organization_id',
        'project_id',
        'invited_by',
        'invited_by_user_id',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_by',
        'accepted_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'token_version' => 'integer',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invitation $invitation): void {
            if (
                ($invitation->token === null || $invitation->token === '')
                && ($invitation->token_hash === null || $invitation->token_hash === '')
            ) {
                $invitation->token = Str::random(80);
            }

            if (
                ($invitation->token_hash === null || $invitation->token_hash === '')
                && ($invitation->token !== null && $invitation->token !== '')
            ) {
                $invitation->token_hash = hash('sha256', (string) $invitation->token);
            }

            if ($invitation->token_version === null) {
                $invitation->token_version = ($invitation->token === null || $invitation->token === '')
                    ? self::TOKEN_VERSION_HASH_ONLY
                    : self::TOKEN_VERSION_LEGACY;
            }

            if ($invitation->status === null || $invitation->status === '') {
                $invitation->status = self::STATUS_PENDING;
            }

            if ($invitation->expires_at === null) {
                $invitation->expires_at = Carbon::now()->addDays(7);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'invitation_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now());
    }

    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    public function scopeByOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function getFullNameAttribute(): string
    {
        return trim((string) $this->first_name . ' ' . (string) $this->last_name);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }

    public function getIsValidAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->is_expired;
    }

    public function getInvitationUrlAttribute(): string
    {
        return route('invitations.accept', ['token' => $this->token, 'team' => $this->team_id]);
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return $this->expires_at?->diffInDays(now()) ?? 0;
    }

    public function markAsAccepted(?string $userId = null): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'accepted_by_user_id' => $userId,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function markAsCancelled(?string $userId = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'revoked_at' => now(),
            'revoked_by_user_id' => $userId,
        ]);
    }

    public function extendExpiry(int $days = 7): void
    {
        $this->update([
            'expires_at' => Carbon::now()->addDays($days),
        ]);
    }

    public function resend(): void
    {
        $token = Str::random(80);

        $this->update([
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'token_version' => self::TOKEN_VERSION_LEGACY,
            'expires_at' => Carbon::now()->addDays(7),
            'status' => self::STATUS_PENDING,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
        ]);
    }

    public function getRoleDisplayName(): string
    {
        $roles = $this->organization->getAvailableRoles();

        return $roles[$this->role] ?? (string) $this->role;
    }

    public function canBeAccepted(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->is_expired;
    }

    public function getProjectName(): string
    {
        return $this->project ? (string) $this->project->name : 'General';
    }

    public function getInviterName(): string
    {
        return $this->inviter ? (string) $this->inviter->name : 'System';
    }

    public static function findByToken(string $token): ?self
    {
        $providedHash = hash('sha256', $token);

        $hashed = static::where('token_hash', $providedHash)->first();
        if ($hashed instanceof self) {
            return $hashed;
        }

        $legacy = static::query()
            ->whereNull('token_hash')
            ->whereNotNull('token')
            ->where('token', $token)
            ->first();

        if ($legacy instanceof self) {
            $legacy->forceFill([
                'token_hash' => $providedHash,
                'token_version' => self::TOKEN_VERSION_HASH_ONLY,
            ])->save();
        }

        return $legacy;
    }

    /** @param array<string, mixed> $data */
    public static function createInvitation(array $data): self
    {
        return static::create(array_merge($data, [
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->addDays(7),
            'status' => self::STATUS_PENDING,
        ]));
    }

    public static function cleanupExpired(): int
    {
        return static::where('status', self::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => self::STATUS_EXPIRED]);
    }
}
