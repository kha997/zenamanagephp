<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CacheEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'expires_at',
        'tags',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Scope for expired entries
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope for valid entries
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope for entries with specific tags
     */
    public function scopeWithTags($query, array $tags)
    {
        return $query->whereJsonContains('tags', $tags);
    }

    /**
     * Check if entry is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Get time until expiration
     */
    public function getTimeUntilExpiration(): int
    {
        return max(0, $this->expires_at->diffInSeconds(now()));
    }

    /**
     * Clean up expired entries
     */
    public static function cleanupExpired(): int
    {
        return static::expired()->delete();
    }
}