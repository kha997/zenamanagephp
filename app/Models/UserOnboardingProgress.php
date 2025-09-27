<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOnboardingProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'onboarding_step_id',
        'status',
        'completed_at',
        'skipped_at',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
        'completed_at' => 'datetime',
        'skipped_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function onboardingStep()
    {
        return $this->belongsTo(OnboardingStep::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeSkipped($query)
    {
        return $query->where('status', 'skipped');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function markAsCompleted(array $data = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'data' => array_merge($this->data ?? [], $data)
        ]);
    }

    public function markAsSkipped(array $data = []): void
    {
        $this->update([
            'status' => 'skipped',
            'skipped_at' => now(),
            'data' => array_merge($this->data ?? [], $data)
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
