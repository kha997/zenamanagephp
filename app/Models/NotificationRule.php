<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'tenant_id',
        'created_by',
        'name',
        'description',
        'event_type', // 'task_created', 'task_completed', 'deadline_approaching', 'change_requested', etc.
        'conditions', // JSON array of conditions
        'notification_channels', // ['email', 'in_app', 'sms', 'webhook']
        'recipients', // JSON array of recipient rules
        'template', // JSON template for notification content
        'is_active',
        'priority', // 'low', 'medium', 'high', 'critical'
        'cooldown_minutes', // prevent spam notifications
        'last_triggered_at',
        'trigger_count',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'conditions' => 'array',
        'notification_channels' => 'array',
        'recipients' => 'array',
        'template' => 'array',
        'is_active' => 'boolean',
        'cooldown_minutes' => 'integer',
        'trigger_count' => 'integer',
        'last_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    public function scopeWithChannel($query, $channel)
    {
        return $query->whereJsonContains('notification_channels', $channel);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isHighPriority(): bool
    {
        return in_array($this->priority, ['high', 'critical']);
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->notification_channels ?? []);
    }

    public function canTrigger(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check cooldown period
        if ($this->last_triggered_at && $this->cooldown_minutes > 0) {
            $cooldownEnd = $this->last_triggered_at->addMinutes($this->cooldown_minutes);
            if (now()->isBefore($cooldownEnd)) {
                return false;
            }
        }

        return true;
    }

    public function incrementTriggerCount(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    public function evaluateConditions(array $eventData): bool
    {
        if (empty($this->conditions)) {
            return true; // No conditions means always trigger
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $eventData)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateCondition(array $condition, array $eventData): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field || !isset($eventData[$field])) {
            return false;
        }

        $eventValue = $eventData[$field];

        switch ($operator) {
            case 'equals':
                return $eventValue === $value;
            case 'not_equals':
                return $eventValue !== $value;
            case 'greater_than':
                return $eventValue > $value;
            case 'less_than':
                return $eventValue < $value;
            case 'contains':
                return str_contains($eventValue, $value);
            case 'in':
                return in_array($eventValue, (array)$value);
            case 'not_in':
                return !in_array($eventValue, (array)$value);
            case 'is_null':
                return is_null($eventValue);
            case 'is_not_null':
                return !is_null($eventValue);
            default:
                return false;
        }
    }

    public function getRecipients(array $eventData): array
    {
        $recipients = [];

        foreach ($this->recipients ?? [] as $recipientRule) {
            $type = $recipientRule['type'] ?? null;
            $value = $recipientRule['value'] ?? null;

            switch ($type) {
                case 'user':
                    $recipients[] = $value;
                    break;
                case 'role':
                    // Get users with this role
                    $users = User::whereHas('roles', function($q) use ($value) {
                        $q->where('name', $value);
                    })->pluck('id')->toArray();
                    $recipients = array_merge($recipients, $users);
                    break;
                case 'project_members':
                    $projectId = $eventData['project_id'] ?? null;
                    if ($projectId) {
                        $users = Project::find($projectId)?->users()->pluck('id')->toArray() ?? [];
                        $recipients = array_merge($recipients, $users);
                    }
                    break;
                case 'task_assignee':
                    $taskId = $eventData['task_id'] ?? null;
                    if ($taskId) {
                        $assigneeId = Task::find($taskId)?->user_id;
                        if ($assigneeId) {
                            $recipients[] = $assigneeId;
                        }
                    }
                    break;
            }
        }

        return array_unique($recipients);
    }

    public function renderTemplate(array $eventData): array
    {
        $template = $this->template ?? [];
        $rendered = [];

        foreach ($template as $key => $value) {
            if (is_string($value)) {
                // Simple variable replacement
                $rendered[$key] = $this->replaceVariables($value, $eventData);
            } elseif (is_array($value)) {
                $rendered[$key] = $this->renderTemplate($value);
            } else {
                $rendered[$key] = $value;
            }
        }

        return $rendered;
    }

    private function replaceVariables(string $template, array $eventData): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($eventData) {
            $variable = $matches[1];
            return $eventData[$variable] ?? $matches[0];
        }, $template);
    }
}
