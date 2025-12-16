<?php declare(strict_types=1);

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * NotificationService - Round 251: Notifications Center Phase 1
 * 
 * Service for creating and managing notifications.
 * 
 * Round 255: Integrated with NotificationPreferenceService to respect user preferences.
 * 
 * Note: Integration logic (calling from tasks/cost/doc) will be implemented in Phase 2.
 */
class NotificationService
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}
    /**
     * Create a notification for a user
     * 
     * @param string $userId User ID to notify
     * @param string $module Module: tasks / documents / cost / rbac / system
     * @param string $type Type: e.g., task.assigned / co.needs_approval
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $entityType Entity type: "task", "change_order", etc.
     * @param string|null $entityId Entity ID (ULID)
     * @param array $metadata Additional metadata
     * @param string|null $tenantId Optional tenant ID (if not provided, will be resolved)
     * @return Notification|null Returns null if notification was skipped due to user preferences
     */
    public function notifyUser(
        string $userId,
        string $module,
        string $type,
        string $title,
        string $message,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
        ?string $tenantId = null
    ): ?Notification {
        // Get tenant_id from parameter, or resolve from context
        if (!$tenantId) {
            $tenantId = $this->resolveTenantId();
        }
        
        // If still no tenant_id, try to get it from the entity
        if (!$tenantId && $entityType && $entityId) {
            $tenantId = $this->resolveTenantIdFromEntity($entityType, $entityId);
        }
        
        // If still no tenant_id, try to get it from the user
        if (!$tenantId) {
            $user = \App\Models\User::find($userId);
            if ($user && $user->tenant_id) {
                $tenantId = (string) $user->tenant_id;
            }
        }
        
        if (!$tenantId) {
            Log::warning('NotificationService::notifyUser: No tenant_id found', [
                'user_id' => $userId,
                'module' => $module,
                'type' => $type,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
            throw new \RuntimeException('Tenant ID is required for notifications');
        }

        // Round 255: Check user preferences before creating notification
        if (!$this->notificationPreferenceService->isTypeEnabledForUser($tenantId, $userId, $type)) {
            Log::debug('NotificationService::notifyUser: Notification skipped due to user preference', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'type' => $type,
            ]);
            return null;
        }
        
        $notification = Notification::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'module' => $module,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => false,
            'metadata' => $metadata,
        ]);
        
        Log::info('Notification created', [
            'notification_id' => $notification->id,
            'user_id' => $userId,
            'module' => $module,
            'type' => $type,
            'tenant_id' => $tenantId,
        ]);
        
        // Round 256: Broadcast notification created event for realtime updates
        // Channel name in backend: tenant.{tenantId}.user.{userId}.notifications
        // Frontend subscribes with: Echo.private('tenant.{tenantId}.user.{userId}.notifications')
        // Event name: notification.created
        event(new NotificationCreated($notification));
        
        return $notification;
    }
    
    /**
     * Resolve tenant ID from current context
     * 
     * @return string|null
     */
    private function resolveTenantId(): ?string
    {
        // Priority 1: Request attribute (set by middleware)
        $request = request();
        $activeTenantId = $request->attributes->get('active_tenant_id');
        if ($activeTenantId) {
            return (string) $activeTenantId;
        }
        
        // Priority 2: Authenticated user's tenant_id
        if (Auth::check()) {
            $user = Auth::user();
            if ($user && $user->tenant_id) {
                return (string) $user->tenant_id;
            }
        }
        
        // Priority 3: TenancyService
        if (app()->bound(\App\Services\TenancyService::class)) {
            $tenancyService = app(\App\Services\TenancyService::class);
            if (Auth::check()) {
                $resolvedTenantId = $tenancyService->resolveActiveTenantId(Auth::user(), $request);
                if ($resolvedTenantId) {
                    return (string) $resolvedTenantId;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Resolve tenant ID from entity
     * 
     * @param string $entityType Entity type
     * @param string $entityId Entity ID
     * @return string|null
     */
    private function resolveTenantIdFromEntity(string $entityType, string $entityId): ?string
    {
        try {
            switch ($entityType) {
                case 'task':
                    $task = \App\Models\ProjectTask::withoutGlobalScope('tenant')->find($entityId);
                    if ($task && $task->tenant_id) {
                        return (string) $task->tenant_id;
                    }
                    break;
                case 'change_order':
                    $co = \App\Models\ChangeOrder::withoutGlobalScope('tenant')->find($entityId);
                    if ($co && $co->tenant_id) {
                        return (string) $co->tenant_id;
                    }
                    break;
                case 'payment_certificate':
                    $cert = \App\Models\ContractPaymentCertificate::withoutGlobalScope('tenant')->find($entityId);
                    if ($cert && $cert->tenant_id) {
                        return (string) $cert->tenant_id;
                    }
                    break;
                case 'payment':
                    $payment = \App\Models\ContractActualPayment::withoutGlobalScope('tenant')->find($entityId);
                    if ($payment && $payment->tenant_id) {
                        return (string) $payment->tenant_id;
                    }
                    break;
                case 'role_profile':
                    $profile = \App\Models\RoleProfile::withoutGlobalScope('tenant')->find($entityId);
                    if ($profile && $profile->tenant_id) {
                        return (string) $profile->tenant_id;
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('NotificationService::resolveTenantIdFromEntity: Failed to resolve tenant_id', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }
        
        return null;
    }
    
    /**
     * Get notifications for a user with filters
     * 
     * @param string $userId User ID
     * @param string $tenantId Tenant ID
     * @param array $filters Filters: is_read, module, search
     * @param int $perPage Items per page
     * @param int $page Page number
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getNotificationsForUser(
        string $userId,
        string $tenantId,
        array $filters = [],
        int $perPage = 20,
        int $page = 1
    ) {
        $query = Notification::forUser($userId)
            ->where('tenant_id', $tenantId)
            ->latest();
        
        // Filter by is_read
        if (isset($filters['is_read'])) {
            $isRead = filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN);
            if ($isRead) {
                $query->read();
            } else {
                $query->unread();
            }
        }
        
        // Filter by module
        if (!empty($filters['module'])) {
            $query->forModule($filters['module']);
        }
        
        // Search by title or message
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
    
    /**
     * Mark notification as read
     * 
     * @param string $notificationId Notification ID
     * @param string $userId User ID (for authorization check)
     * @param string $tenantId Tenant ID (for authorization check)
     * @return bool
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function markAsRead(string $notificationId, string $userId, string $tenantId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        return $notification->markAsRead();
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param string $userId User ID
     * @param string $tenantId Tenant ID
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(string $userId, string $tenantId): int
    {
        return Notification::markAllAsReadForUser($userId, $tenantId);
    }
    
    /**
     * Get unread count for a user
     * 
     * @param string $userId User ID
     * @param string $tenantId Tenant ID
     * @return int
     */
    public function getUnreadCount(string $userId, string $tenantId): int
    {
        return Notification::getUnreadCount($userId, $tenantId);
    }
}
