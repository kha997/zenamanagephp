<?php

namespace App\Services;

use App\Models\NotificationRule;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class NotificationRuleService
{
    /**
     * Create a new notification rule
     */
    public function createRule(array $data): NotificationRule
    {
        $rule = NotificationRule::create([
            'id' => \Str::ulid(),
            'tenant_id' => $data['tenant_id'],
            'created_by' => $data['created_by'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'event_type' => $data['event_type'],
            'conditions' => $data['conditions'] ?? [],
            'notification_channels' => $data['notification_channels'] ?? ['in_app'],
            'recipients' => $data['recipients'] ?? [],
            'template' => $data['template'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 'medium',
            'cooldown_minutes' => $data['cooldown_minutes'] ?? 0,
        ]);

        Log::info("Notification rule created: {$rule->name} for event: {$rule->event_type}");
        
        return $rule;
    }

    /**
     * Update notification rule
     */
    public function updateRule(string $ruleId, array $data): NotificationRule
    {
        $rule = NotificationRule::findOrFail($ruleId);
        $rule->update($data);
        
        Log::info("Notification rule updated: {$rule->name}");
        
        return $rule;
    }

    /**
     * Delete notification rule
     */
    public function deleteRule(string $ruleId): bool
    {
        $rule = NotificationRule::findOrFail($ruleId);
        $ruleName = $rule->name;
        $rule->delete();
        
        Log::info("Notification rule deleted: {$ruleName}");
        
        return true;
    }

    /**
     * Evaluate and trigger notification rules for an event
     */
    public function evaluateRules(string $eventType, array $eventData): void
    {
        $rules = NotificationRule::active()
            ->forEvent($eventType)
            ->get();

        foreach ($rules as $rule) {
            if ($this->shouldTriggerRule($rule, $eventData)) {
                $this->triggerRule($rule, $eventData);
            }
        }
    }

    /**
     * Check if rule should be triggered
     */
    private function shouldTriggerRule(NotificationRule $rule, array $eventData): bool
    {
        // Check if rule can trigger (cooldown, active status)
        if (!$rule->canTrigger()) {
            return false;
        }

        // Evaluate conditions
        if (!$rule->evaluateConditions($eventData)) {
            return false;
        }

        return true;
    }

    /**
     * Trigger notification rule
     */
    private function triggerRule(NotificationRule $rule, array $eventData): void
    {
        try {
            // Get recipients
            $recipients = $rule->getRecipients($eventData);
            
            if (empty($recipients)) {
                Log::warning("No recipients found for rule: {$rule->name}");
                return;
            }

            // Render template
            $renderedTemplate = $rule->renderTemplate($eventData);

            // Send notifications through different channels
            foreach ($rule->notification_channels as $channel) {
                $this->sendNotification($channel, $recipients, $renderedTemplate, $eventData);
            }

            // Update rule trigger count
            $rule->incrementTriggerCount();

            Log::info("Notification rule triggered: {$rule->name} for {$eventType}");

        } catch (\Exception $e) {
            Log::error("Failed to trigger notification rule {$rule->name}: " . $e->getMessage());
        }
    }

    /**
     * Send notification through specific channel
     */
    private function sendNotification(string $channel, array $recipients, array $template, array $eventData): void
    {
        switch ($channel) {
            case 'email':
                $this->sendEmailNotification($recipients, $template, $eventData);
                break;
            case 'in_app':
                $this->sendInAppNotification($recipients, $template, $eventData);
                break;
            case 'sms':
                $this->sendSmsNotification($recipients, $template, $eventData);
                break;
            case 'webhook':
                $this->sendWebhookNotification($recipients, $template, $eventData);
                break;
            default:
                Log::warning("Unknown notification channel: {$channel}");
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(array $recipients, array $template, array $eventData): void
    {
        $users = User::whereIn('id', $recipients)->get();
        
        foreach ($users as $user) {
            if ($user->email) {
                Queue::push(function($job) use ($user, $template, $eventData) {
                    Mail::send('emails.notification', [
                        'user' => $user,
                        'template' => $template,
                        'eventData' => $eventData
                    ], function($message) use ($user, $template) {
                        $message->to($user->email)
                               ->subject($template['subject'] ?? 'Notification');
                    });
                    
                    $job->delete();
                });
            }
        }
    }

    /**
     * Send in-app notification
     */
    private function sendInAppNotification(array $recipients, array $template, array $eventData): void
    {
        // This would typically use a real-time system like WebSocket or Pusher
        // For now, we'll log it
        Log::info("In-app notification sent to users: " . implode(',', $recipients));
        
        // TODO: Implement real-time notification system
        // Example: broadcast(new NotificationSent($recipients, $template, $eventData));
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(array $recipients, array $template, array $eventData): void
    {
        $users = User::whereIn('id', $recipients)->whereNotNull('phone')->get();
        
        foreach ($users as $user) {
            // TODO: Implement SMS service (Twilio, etc.)
            Log::info("SMS notification would be sent to: {$user->phone}");
        }
    }

    /**
     * Send webhook notification
     */
    private function sendWebhookNotification(array $recipients, array $template, array $eventData): void
    {
        // TODO: Implement webhook system
        Log::info("Webhook notification would be sent for recipients: " . implode(',', $recipients));
    }

    /**
     * Get rules for specific event type
     */
    public function getRulesForEvent(string $eventType): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationRule::active()
            ->forEvent($eventType)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get all active rules
     */
    public function getActiveRules(): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationRule::active()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Test notification rule
     */
    public function testRule(string $ruleId, array $testEventData): array
    {
        $rule = NotificationRule::findOrFail($ruleId);
        
        $result = [
            'rule_name' => $rule->name,
            'event_type' => $rule->event_type,
            'can_trigger' => $rule->canTrigger(),
            'conditions_met' => $rule->evaluateConditions($testEventData),
            'recipients' => $rule->getRecipients($testEventData),
            'rendered_template' => $rule->renderTemplate($testEventData),
            'channels' => $rule->notification_channels,
        ];
        
        return $result;
    }

    /**
     * Create default notification rules for a tenant
     */
    public function createDefaultRules(string $tenantId, int $createdBy): void
    {
        $defaultRules = [
            [
                'name' => 'Task Assignment Notification',
                'description' => 'Notify when a task is assigned to a user',
                'event_type' => 'task_assigned',
                'conditions' => [],
                'notification_channels' => ['in_app', 'email'],
                'recipients' => [
                    ['type' => 'task_assignee']
                ],
                'template' => [
                    'subject' => 'New Task Assignment',
                    'message' => 'You have been assigned to task: {{task_name}}',
                    'priority' => 'medium'
                ],
                'priority' => 'medium',
                'cooldown_minutes' => 0,
            ],
            [
                'name' => 'Task Deadline Approaching',
                'description' => 'Notify when task deadline is approaching',
                'event_type' => 'deadline_approaching',
                'conditions' => [
                    ['field' => 'days_until_deadline', 'operator' => 'less_than', 'value' => 3]
                ],
                'notification_channels' => ['in_app', 'email'],
                'recipients' => [
                    ['type' => 'task_assignee']
                ],
                'template' => [
                    'subject' => 'Task Deadline Approaching',
                    'message' => 'Task "{{task_name}}" deadline is approaching in {{days_until_deadline}} days',
                    'priority' => 'high'
                ],
                'priority' => 'high',
                'cooldown_minutes' => 1440, // 24 hours
            ],
            [
                'name' => 'Project Status Change',
                'description' => 'Notify project members when project status changes',
                'event_type' => 'project_status_changed',
                'conditions' => [],
                'notification_channels' => ['in_app'],
                'recipients' => [
                    ['type' => 'project_members']
                ],
                'template' => [
                    'subject' => 'Project Status Updated',
                    'message' => 'Project "{{project_name}}" status changed to {{new_status}}',
                    'priority' => 'medium'
                ],
                'priority' => 'medium',
                'cooldown_minutes' => 0,
            ],
        ];

        foreach ($defaultRules as $ruleData) {
            $ruleData['tenant_id'] = $tenantId;
            $ruleData['created_by'] = $createdBy;
            $this->createRule($ruleData);
        }

        Log::info("Default notification rules created for tenant: {$tenantId}");
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(string $tenantId): array
    {
        $rules = NotificationRule::where('tenant_id', $tenantId)->get();
        
        return [
            'total_rules' => $rules->count(),
            'active_rules' => $rules->where('is_active', true)->count(),
            'inactive_rules' => $rules->where('is_active', false)->count(),
            'total_triggers' => $rules->sum('trigger_count'),
            'rules_by_event' => $rules->groupBy('event_type')->map->count(),
            'rules_by_priority' => $rules->groupBy('priority')->map->count(),
        ];
    }
}