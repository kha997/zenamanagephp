<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Services\ErrorHandlingService;

/**
 * Simplified Error Handling Test
 * 
 * Tests basic error handling functionality without complex dependencies.
 */
class SimplifiedErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test error handling service basic functionality
     */
    public function test_error_handling_service_basic_functionality(): void
    {
        $errorHandlingService = app(ErrorHandlingService::class);
        $request = Request::create('/test', 'GET');
        $exception = new \Exception('Test exception');
        
        $response = $errorHandlingService->handleError($exception, $request);
        
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('id', $data['error']);
        $this->assertArrayHasKey('code', $data['error']);
        $this->assertArrayHasKey('message', $data['error']);
        $this->assertArrayHasKey('details', $data['error']);
    }

    /**
     * Test static error method
     */
    public function test_static_error_method(): void
    {
        $response = ErrorHandlingService::error(
            'E400.BAD_REQUEST',
            'Invalid request',
            ['field' => 'value'],
            400
        );
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('E400.BAD_REQUEST', $data['error']['code']);
        $this->assertEquals('Invalid request', $data['error']['message']);
        $this->assertEquals(['field' => 'value'], $data['error']['details']);
    }

    /**
     * Test error response headers
     */
    public function test_error_response_headers(): void
    {
        $response = ErrorHandlingService::error(
            'E429.RATE_LIMITED',
            'Too many requests',
            [],
            429
        );
        
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-Request-ID'));
        $this->assertTrue($response->headers->has('Retry-After'));
        $this->assertEquals('60', $response->headers->get('Retry-After'));
    }

    /**
     * Test error handling with different status codes
     */
    public function test_error_handling_different_status_codes(): void
    {
        $statusCodes = [400, 401, 403, 404, 422, 429, 500, 503];
        
        foreach ($statusCodes as $statusCode) {
            $response = ErrorHandlingService::error(
                "E{$statusCode}.TEST_ERROR",
                'Test error message',
                [],
                $statusCode
            );
            
            $this->assertEquals($statusCode, $response->getStatusCode());
            
            $data = $response->getData(true);
            $this->assertFalse($data['success']);
            $this->assertArrayHasKey('error', $data);
        }
    }

    /**
     * Test error handling performance
     */
    public function test_error_handling_performance(): void
    {
        $startTime = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            ErrorHandlingService::error(
                'E500.INTERNAL_ERROR',
                'Test performance error',
                [],
                500
            );
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should handle 100 errors in less than 1 second
        $this->assertLessThan(1.0, $executionTime);
    }
}
