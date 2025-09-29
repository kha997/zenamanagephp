<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Admin\SecurityApiController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SecurityApiControllerUnitTest extends TestCase
{
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SecurityApiController();
    }

    /** @test */
    public function it_validates_period_parameter()
    {
        $this->assertEquals('30d', $this->controller->validatePeriod('30d'));
        $this->assertEquals('7d', $this->controller->validatePeriod('7d'));
        $this->assertEquals('90d', $this->controller->validatePeriod('90d'));
        
        // Invalid periods should default to 30d
        $this->assertEquals('30d', $this->controller->validatePeriod('invalid'));
        $this->assertEquals('30d', $this->controller->validatePeriod(''));
        $this->assertEquals('30d', $this->controller->validatePeriod(null));
        $this->assertEquals('30d', $this->controller->validatePeriod('365d'));
    }

    /** @test */
    public function it_maps_actions_to_severity()
    {
        $this->assertEquals('high', $this->controller->mapActionToSeverity('login_failed'));
        $this->assertEquals('high', $this->controller->mapActionToSeverity('password_reset'));
        $this->assertEquals('high', $this->controller->mapActionToSeverity('mfa_disabled'));
        
        $this->assertEquals('medium', $this->controller->mapActionToSeverity('user_created'));
        $this->assertEquals('medium', $this->controller->mapActionToSeverity('user_updated'));
        $this->assertEquals('medium', $this->controller->mapActionToSeverity('role_assigned'));
        
        $this->assertEquals('low', $this->controller->mapActionToSeverity('login'));
        $this->assertEquals('low', $this->controller->mapActionToSeverity('logout'));
        $this->assertEquals('low', $this->controller->mapActionToSeverity('profile_updated'));
        
        $this->assertEquals('info', $this->controller->mapActionToSeverity('unknown_action'));
    }

    /** @test */
    public function it_gets_actions_by_severity()
    {
        $highActions = $this->controller->getActionsBySeverity('high');
        $this->assertContains('login_failed', $highActions);
        $this->assertContains('password_reset', $highActions);
        $this->assertContains('mfa_disabled', $highActions);
        
        $mediumActions = $this->controller->getActionsBySeverity('medium');
        $this->assertContains('user_created', $mediumActions);
        $this->assertContains('user_updated', $mediumActions);
        $this->assertContains('role_assigned', $mediumActions);
        
        $lowActions = $this->controller->getActionsBySeverity('low');
        $this->assertContains('login', $lowActions);
        $this->assertContains('logout', $lowActions);
        $this->assertContains('profile_updated', $lowActions);
        
        $infoActions = $this->controller->getActionsBySeverity('info');
        $this->assertEmpty($infoActions);
    }

    /** @test */
    public function it_enforces_rate_limits()
    {
        $userId = 1;
        $endpoint = 'audit/export';
        $limit = 10;
        $window = 60;
        
        // Clear any existing rate limits
        RateLimiter::clear("security_{$endpoint}:{$userId}");
        
        // Should allow requests up to limit
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($this->controller->enforceRateLimit($userId, $endpoint, $limit, $window));
        }
        
        // Should block requests beyond limit
        $this->assertFalse($this->controller->enforceRateLimit($userId, $endpoint, $limit, $window));
    }

    /** @test */
    public function it_generates_etag_for_responses()
    {
        $data = ['test' => 'data'];
        $etag = $this->controller->generateETag($data);
        
        $this->assertIsString($etag);
        $this->assertNotEmpty($etag);
        
        // Same data should generate same ETag
        $etag2 = $this->controller->generateETag($data);
        $this->assertEquals($etag, $etag2);
        
        // Different data should generate different ETag
        $differentData = ['test' => 'different'];
        $etag3 = $this->controller->generateETag($differentData);
        $this->assertNotEquals($etag, $etag3);
    }

    /** @test */
    public function it_handles_conditional_requests()
    {
        $data = ['test' => 'data'];
        $etag = $this->controller->generateETag($data);
        
        // Mock request with If-None-Match header
        $request = Request::create('/test', 'GET');
        $request->headers->set('If-None-Match', $etag);
        
        // Should return 304 for matching ETag
        $response = $this->controller->handleConditionalRequest($request, $data);
        $this->assertEquals(304, $response->getStatusCode());
        
        // Should return 200 for non-matching ETag
        $request->headers->set('If-None-Match', 'different-etag');
        $response = $this->controller->handleConditionalRequest($request, $data);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_escapes_csv_injection()
    {
        $maliciousData = [
            'action' => '=1+2',
            'user' => '@cmd',
            'ip' => '+cmd',
            'details' => '-cmd'
        ];
        
        $escapedData = $this->controller->escapeCsvInjection($maliciousData);
        
        $this->assertEquals('"=1+2"', $escapedData['action']);
        $this->assertEquals('"@cmd"', $escapedData['user']);
        $this->assertEquals('"+cmd"', $escapedData['ip']);
        $this->assertEquals('"-cmd"', $escapedData['details']);
    }

    /** @test */
    public function it_validates_date_range()
    {
        $this->assertTrue($this->controller->validateDateRange(
            '2025-09-01T00:00:00Z',
            '2025-09-30T23:59:59Z',
            90
        ));
        
        $this->assertFalse($this->controller->validateDateRange(
            '2025-09-30T00:00:00Z',
            '2025-09-01T00:00:00Z',
            90
        ));
        
        $this->assertFalse($this->controller->validateDateRange(
            '2025-01-01T00:00:00Z',
            '2025-12-31T23:59:59Z',
            90
        ));
    }

    /** @test */
    public function it_generates_historical_data()
    {
        $days = 30;
        $data = $this->controller->generateHistoricalData($days);
        
        $this->assertIsArray($data);
        $this->assertCount($days, $data);
        
        // Each data point should have required fields
        foreach ($data as $point) {
            $this->assertArrayHasKey('ts', $point);
            $this->assertArrayHasKey('value', $point);
            $this->assertIsString($point['ts']);
            $this->assertIsNumeric($point['value']);
        }
    }

    /** @test */
    public function it_calculates_kpi_metrics()
    {
        $metrics = $this->controller->calculateKpiMetrics();
        
        $this->assertArrayHasKey('mfa_adoption', $metrics);
        $this->assertArrayHasKey('failed_logins', $metrics);
        $this->assertArrayHasKey('locked_accounts', $metrics);
        $this->assertArrayHasKey('active_sessions', $metrics);
        $this->assertArrayHasKey('risky_keys', $metrics);
        
        // Each metric should have required fields
        foreach ($metrics as $metric) {
            $this->assertArrayHasKey('value', $metric);
            $this->assertArrayHasKey('deltaPct', $metric);
            $this->assertArrayHasKey('deltaAbs', $metric);
            $this->assertArrayHasKey('series', $metric);
            $this->assertIsNumeric($metric['value']);
            $this->assertIsArray($metric['series']);
        }
    }

    /** @test */
    public function it_handles_export_throttling()
    {
        $userId = 1;
        $endpoint = 'audit/export';
        
        // Clear rate limits
        RateLimiter::clear("security_{$endpoint}:{$userId}");
        
        // Should allow export initially
        $this->assertTrue($this->controller->canExport($userId, $endpoint));
        
        // Simulate multiple exports
        for ($i = 0; $i < 10; $i++) {
            $this->controller->recordExport($userId, $endpoint);
        }
        
        // Should throttle after limit
        $this->assertFalse($this->controller->canExport($userId, $endpoint));
    }

    /** @test */
    public function it_generates_retry_after_header()
    {
        $userId = 1;
        $endpoint = 'audit/export';
        
        // Clear rate limits
        RateLimiter::clear("security_{$endpoint}:{$userId}");
        
        // Exceed rate limit
        for ($i = 0; $i < 11; $i++) {
            $this->controller->recordExport($userId, $endpoint);
        }
        
        $retryAfter = $this->controller->getRetryAfter($userId, $endpoint);
        
        $this->assertIsNumeric($retryAfter);
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    /** @test */
    public function it_handles_broadcast_event_creation()
    {
        $eventData = [
            'type' => 'login_failed',
            'email' => 'test@example.com',
            'ip' => '192.168.1.100',
            'country' => 'US'
        ];
        
        $event = $this->controller->createBroadcastEvent($eventData);
        
        $this->assertInstanceOf(LoginFailed::class, $event);
        $this->assertEquals('test@example.com', $event->email);
        $this->assertEquals('192.168.1.100', $event->ip);
        $this->assertEquals('US', $event->country);
    }

    /** @test */
    public function it_handles_feature_flag_checks()
    {
        // Test with feature flag enabled
        config(['security.allow_test_event' => true]);
        $this->assertTrue($this->controller->isTestEventAllowed());
        
        // Test with feature flag disabled
        config(['security.allow_test_event' => false]);
        $this->assertFalse($this->controller->isTestEventAllowed());
    }

    /** @test */
    public function it_generates_correlation_id()
    {
        $correlationId = $this->controller->generateCorrelationId();
        
        $this->assertIsString($correlationId);
        $this->assertNotEmpty($correlationId);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $correlationId);
    }

    /** @test */
    public function it_handles_error_responses()
    {
        $error = [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Invalid parameter',
            'details' => ['field' => 'period']
        ];
        
        $response = $this->controller->errorResponse($error, 422);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals($error, $responseData['error']);
    }
}
