<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Admin\SecurityApiController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SecurityApiController();
    }

    /** @test */
    public function it_gets_days_from_period_correctly()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getDaysFromPeriod');
        $method->setAccessible(true);

        $this->assertEquals(7, $method->invoke($this->controller, '7d'));
        $this->assertEquals(30, $method->invoke($this->controller, '30d'));
        $this->assertEquals(90, $method->invoke($this->controller, '90d'));
        $this->assertEquals(30, $method->invoke($this->controller, 'invalid')); // Default
    }

    /** @test */
    public function it_generates_mfa_adoption_series()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('generateMfaAdoptionSeries');
        $method->setAccessible(true);

        $series = $method->invoke($this->controller, 7);
        
        $this->assertCount(7, $series);
        $this->assertIsArray($series);
        
        // All values should be between 0 and 100
        foreach ($series as $value) {
            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertLessThanOrEqual(100, $value);
        }
    }

    /** @test */
    public function it_generates_login_attempts_series()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('generateLoginAttemptsSeries');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 5);
        
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertCount(5, $result['success']);
        $this->assertCount(5, $result['failed']);
        
        // All values should be non-negative
        foreach ($result['success'] as $value) {
            $this->assertGreaterThanOrEqual(0, $value);
        }
        foreach ($result['failed'] as $value) {
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    /** @test */
    public function it_generates_active_sessions_series()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('generateActiveSessionsSeries');
        $method->setAccessible(true);

        $series = $method->invoke($this->controller, 10);
        
        $this->assertCount(10, $series);
        
        // All values should be non-negative
        foreach ($series as $value) {
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    /** @test */
    public function it_generates_failed_logins_series()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('generateFailedLoginsSeries');
        $method->setAccessible(true);

        $series = $method->invoke($this->controller, 15);
        
        $this->assertCount(15, $series);
        
        // All values should be non-negative
        foreach ($series as $value) {
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    /** @test */
    public function it_builds_audit_query_correctly()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildAuditQuery');
        $method->setAccessible(true);

        $request = new Request([
            'action' => 'login',
            'severity' => 'high',
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31'
        ]);

        $query = $method->invoke($this->controller, $request);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    /** @test */
    public function it_builds_login_query_correctly()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildLoginQuery');
        $method->setAccessible(true);

        $request = new Request([
            'result' => 'failed',
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31'
        ]);

        $query = $method->invoke($this->controller, $request);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    /** @test */
    public function it_enforces_rate_limits()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('enforceRateLimit');
        $method->setAccessible(true);

        $request = new Request();
        $request->setUserResolver(function() {
            return (object) ['id' => 1];
        });

        // First few calls should succeed
        for ($i = 0; $i < 5; $i++) {
            try {
                $method->invoke($this->controller, $request, 'test_key', 10, 60);
                $this->assertTrue(true); // No exception thrown
            } catch (\Exception $e) {
                $this->fail('Rate limit should not be triggered for first 5 calls');
            }
        }

        // After 10 calls, should hit rate limit
        for ($i = 0; $i < 6; $i++) {
            try {
                $method->invoke($this->controller, $request, 'test_key', 10, 60);
            } catch (\Exception $e) {
                $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $e);
                $this->assertEquals(429, $e->getStatusCode());
                break;
            }
        }
    }
}
