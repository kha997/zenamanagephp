<?php

namespace App\Services;
use Illuminate\Support\Facades\Auth;


use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Secure Audit Service
 * 
 * Enhanced audit logging with data masking and security features
 */
class SecureAuditService
{
    /**
     * Log user action with data masking
     */
    public function logAction(
        string $userId,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $projectId = null,
        ?string $tenantId = null
    ): bool {
        try {
            $auditData = [
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId,
                'old_data' => $oldData ? json_encode($this->maskSensitiveData($oldData)) : null,
                'new_data' => $newData ? json_encode($this->maskSensitiveData($newData)) : null,
                'ip_address' => app()->bound('request') ? request()->ip() : '127.0.0.1',
                'user_agent' => app()->bound('request') ? request()->userAgent() : 'CLI',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            
            DB::table('audit_logs')->insert($auditData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Audit log failed', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Log authentication events
     */
    public function logAuthEvent(string $event, array $data = []): void
    {
        $this->logAction(
            userId: $data['user_id'] ?? 'system',
            action: $event,
            entityType: 'Authentication',
            entityId: $data['user_id'] ?? null,
            oldData: null,
            newData: $this->maskSensitiveData($data),
            tenantId: $data['tenant_id'] ?? null
        );
    }

    /**
     * Log CRUD operations
     */
    public function logCrudOperation(
        string $action,
        string $model,
        string $modelId,
        array $oldData = [],
        array $newData = [],
        ?string $userId = null,
        ?string $tenantId = null
    ): void {
        $this->logAction(
            userId: $userId ?? Auth::id() ?? 'system',
            action: $action,
            entityType: $model,
            entityId: $modelId,
            oldData: $oldData,
            newData: $newData,
            tenantId: $tenantId ?? Auth::user()?->tenant_id
        );
    }

    /**
     * Log role/permission changes
     */
    public function logRoleChange(
        string $userId,
        string $targetUserId,
        array $oldRoles = [],
        array $newRoles = [],
        ?string $tenantId = null
    ): void {
        $this->logAction(
            userId: $userId,
            action: 'role_change',
            entityType: 'User',
            entityId: $targetUserId,
            oldData: ['roles' => $oldRoles],
            newData: ['roles' => $newRoles],
            tenantId: $tenantId
        );
    }

    /**
     * Log password changes
     */
    public function logPasswordChange(
        string $userId,
        string $targetUserId,
        ?string $tenantId = null
    ): void {
        $this->logAction(
            userId: $userId,
            action: 'password_change',
            entityType: 'User',
            entityId: $targetUserId,
            oldData: null,
            newData: ['password_changed_at' => now()],
            tenantId: $tenantId
        );
    }

    /**
     * Log login events
     */
    public function logLogin(string $userId, bool $success = true, ?string $tenantId = null): void
    {
        $action = $success ? 'login_success' : 'login_failed';
        
        $this->logAction(
            userId: $userId,
            action: $action,
            entityType: 'Authentication',
            entityId: $userId,
            oldData: null,
            newData: $success ? ['last_login_at' => now()] : null,
            tenantId: $tenantId
        );

        // Update last_login_at for successful logins
        if ($success) {
            User::where('id', $userId)->update(['last_login_at' => now()]);
        }
    }

    /**
     * Log logout events
     */
    public function logLogout(string $userId, ?string $tenantId = null): void
    {
        $this->logAction(
            userId: $userId,
            action: 'logout',
            entityType: 'Authentication',
            entityId: $userId,
            oldData: null,
            newData: ['logout_at' => now()],
            tenantId: $tenantId
        );
    }

    /**
     * Mask sensitive data in audit logs
     */
    private function maskSensitiveData(array $data): array
    {
        $maskedData = $data;
        
        // Fields to mask
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'secret',
            'private_key'
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($maskedData[$field])) {
                $maskedData[$field] = '[MASKED]';
            }
        }

        // Mask email partially
        if (isset($maskedData['email'])) {
            $email = $maskedData['email'];
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                $username = $parts[0];
                $domain = $parts[1];
                
                if (strlen($username) > 2) {
                    $maskedUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
                } else {
                    $maskedUsername = str_repeat('*', strlen($username));
                }
                
                $maskedData['email'] = $maskedUsername . '@' . $domain;
            }
        }

        // Mask phone numbers
        if (isset($maskedData['phone'])) {
            $phone = $maskedData['phone'];
            if (strlen($phone) > 4) {
                $maskedData['phone'] = str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
            } else {
                $maskedData['phone'] = str_repeat('*', strlen($phone));
            }
        }

        return $maskedData;
    }

    /**
     * Get audit logs for a user (with tenant isolation)
     */
    public function getUserAuditLogs(
        string $userId,
        ?string $tenantId = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = DB::table('audit_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get audit logs for an entity
     */
    public function getEntityAuditLogs(
        string $entityType,
        string $entityId,
        ?string $tenantId = null,
        int $limit = 50
    ): array {
        $query = DB::table('audit_logs')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->toArray();
    }
}