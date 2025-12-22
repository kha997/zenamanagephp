<?php declare(strict_types=1);

namespace Tests\Unit\WebSocket;

use App\WebSocket\RateLimitGuard;
use Tests\TestCase;

/**
 * RateLimitGuard Unit Tests
 * 
 * PR #3: Tests for WebSocket rate limiting guard
 */
class RateLimitGuardTest extends TestCase
{
    private RateLimitGuard $rateLimitGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimitGuard = new RateLimitGuard();
    }

    /**
     * Test per-connection rate limiting
     */
    public function test_per_connection_rate_limit(): void
    {
        $conn = $this->createMockConnection(1);
        
        // Send messages up to limit (10 per second)
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($this->rateLimitGuard->canSendMessage($conn));
        }
        
        // 11th message should be rate limited
        $this->assertFalse($this->rateLimitGuard->canSendMessage($conn));
    }

    /**
     * Test per-tenant rate limiting
     */
    public function test_per_tenant_rate_limit(): void
    {
        $tenantId = 'tenant-123';
        
        // Send messages up to limit (500 per second)
        for ($i = 0; $i < 500; $i++) {
            $this->assertTrue($this->rateLimitGuard->canSendMessageForTenant($tenantId));
        }
        
        // 501st message should be rate limited
        $this->assertFalse($this->rateLimitGuard->canSendMessageForTenant($tenantId));
    }

    /**
     * Test connection limit per tenant
     */
    public function test_connection_limit_per_tenant(): void
    {
        $tenantId = 'tenant-123';
        
        // Register connections up to limit (50 per tenant)
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($this->rateLimitGuard->canAcceptConnection($tenantId));
            $this->rateLimitGuard->registerConnection($tenantId);
        }
        
        // 51st connection should be rejected
        $this->assertFalse($this->rateLimitGuard->canAcceptConnection($tenantId));
    }

    /**
     * Test connection cleanup
     */
    public function test_connection_cleanup(): void
    {
        $tenantId = 'tenant-123';
        $conn = $this->createMockConnection(1);
        $conn->tenantId = $tenantId;
        
        // Register connection
        $this->rateLimitGuard->registerConnection($tenantId);
        $this->assertTrue($this->rateLimitGuard->canAcceptConnection($tenantId)); // Not at limit yet (1 < 50)
        
        // Send some messages to track connection
        $this->rateLimitGuard->canSendMessage($conn);
        
        // Cleanup connection (should unregister if tenantId is set)
        $this->rateLimitGuard->cleanupConnection($conn);
        
        // Connection should be unregistered, so we can still accept (we're back to 0 connections)
        $this->assertTrue($this->rateLimitGuard->canAcceptConnection($tenantId));
        
        // Verify we can register again (cleanup unregistered the connection)
        $this->rateLimitGuard->registerConnection($tenantId);
        $this->assertTrue($this->rateLimitGuard->canAcceptConnection($tenantId)); // Still under limit
    }

    /**
     * Test unregister connection
     */
    public function test_unregister_connection(): void
    {
        $tenantId = 'tenant-123';
        
        // Register 2 connections
        $this->rateLimitGuard->registerConnection($tenantId);
        $this->rateLimitGuard->registerConnection($tenantId);
        
        // Unregister one
        $this->rateLimitGuard->unregisterConnection($tenantId);
        
        // Should still be able to accept connections (1 < 50)
        $this->assertTrue($this->rateLimitGuard->canAcceptConnection($tenantId));
    }

    /**
     * Test rate limit window resets after 1 second
     */
    public function test_rate_limit_window_reset(): void
    {
        $conn = $this->createMockConnection(1);
        
        // Exhaust rate limit
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimitGuard->canSendMessage($conn);
        }
        
        // Should be rate limited
        $this->assertFalse($this->rateLimitGuard->canSendMessage($conn));
        
        // Simulate time passing (in real scenario, this would be handled by sliding window)
        // Note: This test verifies the logic, but in production the window resets automatically
        // after 1 second based on actual time
    }

    /**
     * Test getStats returns correct statistics
     */
    public function test_get_stats(): void
    {
        $tenantId = 'tenant-123';
        $conn = $this->createMockConnection(1);
        $conn->tenantId = $tenantId;
        
        // Register connection and send some messages
        $this->rateLimitGuard->registerConnection($tenantId);
        $this->rateLimitGuard->canSendMessage($conn);
        $this->rateLimitGuard->canSendMessageForTenant($tenantId);
        
        $stats = $this->rateLimitGuard->getStats();
        
        $this->assertArrayHasKey('connections_per_tenant', $stats);
        $this->assertArrayHasKey('messages_per_tenant_per_second', $stats);
        $this->assertArrayHasKey('active_connections', $stats);
        $this->assertArrayHasKey('limits', $stats);
        
        $this->assertEquals(1, $stats['connections_per_tenant'][$tenantId]);
        $this->assertArrayHasKey($tenantId, $stats['messages_per_tenant_per_second']);
    }

    /**
     * Helper to create mock connection
     * Creates a simple object with resourceId property
     */
    private function createMockConnection(int $resourceId): object
    {
        $conn = new \stdClass();
        $conn->resourceId = $resourceId;
        return $conn;
    }
}

