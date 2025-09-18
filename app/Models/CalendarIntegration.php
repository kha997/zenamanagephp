<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * CalendarIntegration Model - Calendar integrations cho users
 * 
 * @property string $id ULID primary key
 * @property string $user_id ID người dùng
 * @property string $provider Provider (google, outlook, apple)
 * @property string|null $calendar_id External calendar ID
 * @property string $calendar_name Tên calendar
 * @property string|null $access_token Access token
 * @property string|null $refresh_token Refresh token
 * @property \Carbon\Carbon|null $token_expires_at Token expiration
 * @property array|null $provider_data Provider-specific data
 * @property bool $is_active Trạng thái active
 * @property bool $sync_enabled Sync enabled
 * @property \Carbon\Carbon|null $last_sync_at Last sync time
 */
class CalendarIntegration extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'calendar_integrations';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'user_id',
        'provider',
        'calendar_id',
        'calendar_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'provider_data',
        'is_active',
        'sync_enabled',
        'last_sync_at'
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'provider_data' => 'array',
        'is_active' => 'boolean',
        'sync_enabled' => 'boolean',
        'last_sync_at' => 'datetime'
    ];

    protected $hidden = [
        'access_token',
        'refresh_token'
    ];

    /**
     * Provider constants
     */
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_OUTLOOK = 'outlook';
    public const PROVIDER_APPLE = 'apple';
    public const PROVIDER_CALDAV = 'caldav';

    public const VALID_PROVIDERS = [
        self::PROVIDER_GOOGLE,
        self::PROVIDER_OUTLOOK,
        self::PROVIDER_APPLE,
        self::PROVIDER_CALDAV,
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSyncEnabled($query)
    {
        return $query->where('sync_enabled', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        
        return $this->token_expires_at->isPast();
    }

    /**
     * Check if token needs refresh
     */
    public function needsTokenRefresh(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        
        // Refresh if expires within 5 minutes
        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Update last sync time
     */
    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    /**
     * Get provider configuration
     */
    public function getProviderConfig(): array
    {
        return match($this->provider) {
            self::PROVIDER_GOOGLE => [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect_uri'),
                'scopes' => ['https://www.googleapis.com/auth/calendar']
            ],
            self::PROVIDER_OUTLOOK => [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'redirect_uri' => config('services.microsoft.redirect_uri'),
                'scopes' => ['https://graph.microsoft.com/calendars.readwrite']
            ],
            self::PROVIDER_APPLE => [
                'client_id' => config('services.apple.client_id'),
                'client_secret' => config('services.apple.client_secret'),
                'redirect_uri' => config('services.apple.redirect_uri'),
                'scopes' => ['calendars']
            ],
            default => []
        };
    }

    /**
     * Get calendar events for date range
     */
    public function getEventsForDateRange(Carbon $startDate, Carbon $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return $this->events()
                   ->whereBetween('start_time', [$startDate, $endDate])
                   ->orderBy('start_time')
                   ->get();
    }

    /**
     * Get upcoming events
     */
    public function getUpcomingEvents(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->events()
                   ->where('start_time', '>=', now())
                   ->orderBy('start_time')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Check if integration is healthy
     */
    public function isHealthy(): bool
    {
        return $this->is_active && 
               $this->sync_enabled && 
               !$this->isTokenExpired() &&
               $this->access_token;
    }

    /**
     * Get integration status
     */
    public function getStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        
        if (!$this->sync_enabled) {
            return 'sync_disabled';
        }
        
        if ($this->isTokenExpired()) {
            return 'token_expired';
        }
        
        if (!$this->access_token) {
            return 'not_connected';
        }
        
        return 'healthy';
    }

    /**
     * Accessors
     */
    public function getProviderLabelAttribute(): string
    {
        return match($this->provider) {
            self::PROVIDER_GOOGLE => 'Google Calendar',
            self::PROVIDER_OUTLOOK => 'Microsoft Outlook',
            self::PROVIDER_APPLE => 'Apple Calendar',
            self::PROVIDER_CALDAV => 'CalDAV',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->getStatus()) {
            'healthy' => 'green',
            'inactive' => 'gray',
            'sync_disabled' => 'yellow',
            'token_expired' => 'red',
            'not_connected' => 'red',
            default => 'gray'
        };
    }

    public function getLastSyncAgoAttribute(): string
    {
        if (!$this->last_sync_at) {
            return 'Never';
        }
        
        return $this->last_sync_at->diffForHumans();
    }
}