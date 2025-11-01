<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserSession Model - Tracks active user sessions
 */
class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id',
        'session_id',
        'device_id',
        'device_name',
        'device_type',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'ip_address',
        'country',
        'city',
        'is_current',
        'is_trusted',
        'last_activity_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_current' => 'boolean',
        'is_trusted' => 'boolean',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship with User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
    }

    /**
     * Scope: By user
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Current session
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Calculate risk score based on various factors
     */
    public function calculateRiskScore(): float
    {
        $score = 0;

        // New device
        if (!$this->is_trusted) {
            $score += 30;
        }

        // Old session (> 7 days)
        if ($this->last_activity_at && $this->last_activity_at->diffInDays(now()) > 7) {
            $score += 20;
        }

        // Unusual location (would need geolocation history)
        // This is a placeholder
        
        return min(100, $score);
    }
}
