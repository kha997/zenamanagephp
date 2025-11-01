<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'title',
        'description',
        'type',
        'target_element',
        'position',
        'content',
        'actions',
        'order',
        'is_active',
        'is_required',
        'role'
    ];

    protected $casts = [
        'content' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'is_required' => 'boolean'
    ];

    public function userProgress()
    {
        return $this->hasMany(UserOnboardingProgress::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('role')->orWhere('role', $role);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function getNextStep()
    {
        return static::active()
            ->where('order', '>', $this->order)
            ->ordered()
            ->first();
    }

    public function getPreviousStep()
    {
        return static::active()
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }

    public function isCompletedForUser(User $user): bool
    {
        return $this->userProgress()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->exists();
    }

    public function isSkippedForUser(User $user): bool
    {
        return $this->userProgress()
            ->where('user_id', $user->id)
            ->where('status', 'skipped')
            ->exists();
    }

    public function getProgressForUser(User $user): ?UserOnboardingProgress
    {
        return $this->userProgress()
            ->where('user_id', $user->id)
            ->first();
    }
}
