<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmailTracking extends Model
{
    use HasFactory;

    protected $table = 'email_tracking';

    protected $fillable = [
        'tracking_id',
        'email_type',
        'recipient_email',
        'recipient_name',
        'invitation_id',
        'user_id',
        'organization_id',
        'subject',
        'content_hash',
        'metadata',
        'status',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'failed_at',
        'open_count',
        'click_count',
        'open_details',
        'click_details',
        'error_message',
        'provider_response',
    ];

    protected $casts = [
        'metadata' => 'array',
        'open_details' => 'array',
        'click_details' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tracking) {
            if (empty($tracking->tracking_id)) {
                $tracking->tracking_id = Str::uuid();
            }
        });
    }

    /**
     * Get the invitation that this email tracking belongs to.
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get the user that this email tracking belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organization that this email tracking belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Mark email as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark email as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark email as opened
     */
    public function markAsOpened(array $details = []): void
    {
        $this->update([
            'status' => 'opened',
            'opened_at' => $this->opened_at ?: Carbon::now(),
            'open_count' => $this->open_count + 1,
            'open_details' => array_merge($this->open_details ?? [], $details),
        ]);
    }

    /**
     * Mark email as clicked
     */
    public function markAsClicked(array $details = []): void
    {
        $this->update([
            'status' => 'clicked',
            'clicked_at' => $this->clicked_at ?: Carbon::now(),
            'click_count' => $this->click_count + 1,
            'click_details' => array_merge($this->click_details ?? [], $details),
        ]);
    }

    /**
     * Mark email as bounced
     */
    public function markAsBounced(string $reason = null): void
    {
        $this->update([
            'status' => 'bounced',
            'bounced_at' => Carbon::now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Mark email as failed
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => Carbon::now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Check if email has been opened
     */
    public function isOpened(): bool
    {
        return $this->status === 'opened' && $this->opened_at !== null;
    }

    /**
     * Check if email has been clicked
     */
    public function isClicked(): bool
    {
        return $this->status === 'clicked' && $this->clicked_at !== null;
    }

    /**
     * Get delivery time in seconds
     */
    public function getDeliveryTime(): ?int
    {
        if ($this->sent_at && $this->delivered_at) {
            return $this->delivered_at->diffInSeconds($this->sent_at);
        }
        return null;
    }

    /**
     * Get time to open in seconds
     */
    public function getTimeToOpen(): ?int
    {
        if ($this->sent_at && $this->opened_at) {
            return $this->opened_at->diffInSeconds($this->sent_at);
        }
        return null;
    }

    /**
     * Get time to click in seconds
     */
    public function getTimeToClick(): ?int
    {
        if ($this->sent_at && $this->clicked_at) {
            return $this->clicked_at->diffInSeconds($this->sent_at);
        }
        return null;
    }

    /**
     * Get engagement score (0-100)
     */
    public function getEngagementScore(): int
    {
        $score = 0;
        
        if ($this->isOpened()) $score += 30;
        if ($this->isClicked()) $score += 50;
        if ($this->open_count > 1) $score += 10;
        if ($this->click_count > 1) $score += 10;
        
        return min($score, 100);
    }
}