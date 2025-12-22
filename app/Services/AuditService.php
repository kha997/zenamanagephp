<?php

namespace App\Services;

class AuditService
{
    public function log(string $action, string $userId, string $tenantId, array $data = []): bool
    {
        // Simple audit logging
        \Log::info("Audit: {$action}", [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'data' => $data
        ]);
        
        return true;
    }
}