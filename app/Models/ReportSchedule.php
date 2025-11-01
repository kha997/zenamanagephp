<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'description',
        'type',
        'format',
        'frequency',
        'recipients',
        'filters',
        'options',
        'is_active',
        'last_sent_at',
        'next_send_at'
    ];

    protected $casts = [
        'recipients' => 'array',
        'filters' => 'array',
        'options' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
        'next_send_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForSending($query)
    {
        return $query->where('next_send_at', '<=', now());
    }

    public function calculateNextSendDate()
    {
        $now = now();
        
        switch ($this->frequency) {
            case 'daily':
                return $now->addDay();
            case 'weekly':
                return $now->addWeek();
            case 'monthly':
                return $now->addMonth();
            default:
                return $now->addDay();
        }
    }

    public function markAsSent()
    {
        $this->update([
            'last_sent_at' => now(),
            'next_send_at' => $this->calculateNextSendDate()
        ]);
    }
}
