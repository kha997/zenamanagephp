<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'email',
        'first_name',
        'last_name',
        'role',
        'message',
        'organization_id',
        'project_id',
        'invited_by',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_by',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(64);
            }
            
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = Carbon::now()->addDays(7);
            }
        });
    }

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function accepter()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'invitation_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // Accessors & Mutators
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getIsExpiredAttribute()
    {
        return $this->expires_at->isPast();
    }

    public function getIsValidAttribute()
    {
        return $this->status === 'pending' && !$this->is_expired;
    }

    public function getInvitationUrlAttribute()
    {
        return route('invitation.accept', ['token' => $this->token]);
    }

    public function getDaysUntilExpiryAttribute()
    {
        return $this->expires_at->diffInDays(now());
    }

    // Methods
    public function markAsAccepted($userId = null)
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by' => $userId,
        ]);
    }

    public function markAsExpired()
    {
        $this->update(['status' => 'expired']);
    }

    public function markAsCancelled()
    {
        $this->update(['status' => 'cancelled']);
    }

    public function extendExpiry($days = 7)
    {
        $this->update([
            'expires_at' => Carbon::now()->addDays($days)
        ]);
    }

    public function resend()
    {
        // Reset token and expiry
        $this->update([
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->addDays(7),
            'status' => 'pending',
        ]);
    }

    public function getRoleDisplayName()
    {
        $roles = $this->organization->getAvailableRoles();
        return $roles[$this->role] ?? $this->role;
    }

    public function canBeAccepted()
    {
        return $this->status === 'pending' && !$this->is_expired;
    }

    public function getProjectName()
    {
        return $this->project ? $this->project->name : 'General';
    }

    public function getInviterName()
    {
        return $this->inviter ? $this->inviter->name : 'System';
    }

    // Static methods
    public static function findByToken($token)
    {
        return static::where('token', $token)->first();
    }

    public static function createInvitation($data)
    {
        return static::create(array_merge($data, [
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->addDays(7),
        ]));
    }

    public static function cleanupExpired()
    {
        return static::where('status', 'pending')
                    ->where('expires_at', '<', now())
                    ->update(['status' => 'expired']);
    }
}