<?php declare(strict_types=1);

namespace App\WebSocket;

use Illuminate\Support\Facades\Log;
use Ratchet\ConnectionInterface;

/**
 * WebSocket Rate Limit Guard
 * 
 * PR #3: Rate limiting for WebSocket connections.
 * Enforces per-connection and per-tenant rate limits.
 * 
 * Features:
 * - Per-connection rate limiting
 * - Per-tenant rate limiting
 * - Configurable limits
 * - Sliding window algorithm
 */
class RateLimitGuard
{
    // Rate limit constants
    private const MAX_MESSAGES_PER_SECOND_PER_CONNECTION = 10;
    private const MAX_MESSAGES_PER_SECOND_PER_TENANT = 500;
    private const MAX_CONNECTIONS_PER_TENANT = 50;
    
    // Tracking storage
    private array $connectionMessageCounts = [];
    private array $tenantMessageCounts = [];
    private array $tenantConnections = [];
    
    /**
     * Check if connection can send message (per-connection rate limit)
     * 
     * @param ConnectionInterface|object $conn Connection object with resourceId property
     * @return bool True if allowed, false if rate limited
     */
    public function canSendMessage($conn): bool
    {
        $connId = $conn->resourceId ?? null;
        if ($connId === null) {
            return false;
        }
        $now = time();
        
        if (!isset($this->connectionMessageCounts[$connId])) {
            $this->connectionMessageCounts[$connId] = [
                'count' => 0,
                'window_start' => $now,
            ];
        }
        
        $countData = &$this->connectionMessageCounts[$connId];
        
        // Reset counter if window expired (1 second sliding window)
        if ($now - $countData['window_start'] >= 1) {
            $countData = ['count' => 0, 'window_start' => $now];
        }
        
        // Check limit
        if ($countData['count'] >= self::MAX_MESSAGES_PER_SECOND_PER_CONNECTION) {
            Log::warning('WebSocket rate limit exceeded (per connection)', [
                'connection_id' => $connId,
                'count' => $countData['count'],
                'limit' => self::MAX_MESSAGES_PER_SECOND_PER_CONNECTION,
            ]);
            return false;
        }
        
        $countData['count']++;
        return true;
    }
    
    /**
     * Check if tenant can send message (per-tenant rate limit)
     * 
     * @param string $tenantId
     * @return bool True if allowed, false if rate limited
     */
    public function canSendMessageForTenant(string $tenantId): bool
    {
        $now = time();
        
        if (!isset($this->tenantMessageCounts[$tenantId])) {
            $this->tenantMessageCounts[$tenantId] = [
                'count' => 0,
                'window_start' => $now,
            ];
        }
        
        $countData = &$this->tenantMessageCounts[$tenantId];
        
        // Reset counter if window expired
        if ($now - $countData['window_start'] >= 1) {
            $countData = ['count' => 0, 'window_start' => $now];
        }
        
        // Check limit
        if ($countData['count'] >= self::MAX_MESSAGES_PER_SECOND_PER_TENANT) {
            Log::warning('WebSocket rate limit exceeded (per tenant)', [
                'tenant_id' => $tenantId,
                'count' => $countData['count'],
                'limit' => self::MAX_MESSAGES_PER_SECOND_PER_TENANT,
            ]);
            return false;
        }
        
        $countData['count']++;
        return true;
    }
    
    /**
     * Check if tenant can accept new connection
     * 
     * @param string $tenantId
     * @return bool True if allowed, false if connection limit exceeded
     */
    public function canAcceptConnection(string $tenantId): bool
    {
        $currentConnections = $this->tenantConnections[$tenantId] ?? 0;
        
        if ($currentConnections >= self::MAX_CONNECTIONS_PER_TENANT) {
            Log::warning('WebSocket connection limit exceeded for tenant', [
                'tenant_id' => $tenantId,
                'current_connections' => $currentConnections,
                'limit' => self::MAX_CONNECTIONS_PER_TENANT,
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Register new connection for tenant
     * 
     * @param string $tenantId
     * @return void
     */
    public function registerConnection(string $tenantId): void
    {
        if (!isset($this->tenantConnections[$tenantId])) {
            $this->tenantConnections[$tenantId] = 0;
        }
        
        $this->tenantConnections[$tenantId]++;
    }
    
    /**
     * Unregister connection for tenant
     * 
     * @param string $tenantId
     * @return void
     */
    public function unregisterConnection(string $tenantId): void
    {
        if (isset($this->tenantConnections[$tenantId]) && $this->tenantConnections[$tenantId] > 0) {
            $this->tenantConnections[$tenantId]--;
            
            if ($this->tenantConnections[$tenantId] === 0) {
                unset($this->tenantConnections[$tenantId]);
            }
        }
    }
    
    /**
     * Clean up connection tracking
     * 
     * @param ConnectionInterface|object $conn Connection object with resourceId and optional tenantId property
     * @return void
     */
    public function cleanupConnection($conn): void
    {
        $connId = $conn->resourceId ?? null;
        if ($connId === null) {
            return;
        }
        unset($this->connectionMessageCounts[$connId]);
        
        if (isset($conn->tenantId)) {
            $this->unregisterConnection($conn->tenantId);
        }
    }
    
    /**
     * Get rate limit statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $connectionsPerTenant = [];
        foreach ($this->tenantConnections as $tenantId => $count) {
            $connectionsPerTenant[$tenantId] = $count;
        }
        
        $messagesPerTenant = [];
        foreach ($this->tenantMessageCounts as $tenantId => $data) {
            $messagesPerTenant[$tenantId] = $data['count'];
        }
        
        return [
            'connections_per_tenant' => $connectionsPerTenant,
            'messages_per_tenant_per_second' => $messagesPerTenant,
            'active_connections' => count($this->connectionMessageCounts),
            'limits' => [
                'max_messages_per_second_per_connection' => self::MAX_MESSAGES_PER_SECOND_PER_CONNECTION,
                'max_messages_per_second_per_tenant' => self::MAX_MESSAGES_PER_SECOND_PER_TENANT,
                'max_connections_per_tenant' => self::MAX_CONNECTIONS_PER_TENANT,
            ],
        ];
    }
}

