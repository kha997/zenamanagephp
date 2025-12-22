<?php declare(strict_types=1);

namespace Tests\Helpers;

use Illuminate\Testing\TestResponse;

/**
 * ApiResponseAssertions - Shared assertion utilities for API responses
 * 
 * Provides standardized assertions for API error envelopes and X-Request-Id
 * 
 * @package Tests\Helpers
 */
class ApiResponseAssertions
{
    /**
     * Assert API response has standard error envelope structure
     * 
     * @param TestResponse $response
     * @param int $expectedStatus Expected HTTP status code
     * @param string|null $expectedErrorId Expected error ID (optional)
     * @return void
     */
    public static function assertErrorEnvelope(
        TestResponse $response,
        int $expectedStatus,
        ?string $expectedErrorId = null
    ): void {
        $response->assertStatus($expectedStatus);
        
        $json = $response->json();
        
        // Check for standard error envelope structure
        // Format: { status: 'error', error: { id: '...', message: '...' } }
        if (isset($json['status'])) {
            \PHPUnit\Framework\Assert::assertEquals('error', $json['status']);
        }
        
        if (isset($json['error'])) {
            $error = is_array($json['error']) ? $json['error'] : ['message' => $json['error']];
            
            // Assert error has ID (for correlation with logs)
            if (isset($error['id'])) {
                \PHPUnit\Framework\Assert::assertNotEmpty($error['id'], 'Error should have an ID for log correlation');
            }
            
            // Assert error has message
            \PHPUnit\Framework\Assert::assertArrayHasKey('message', $error, 'Error should have a message');
            
            // Check expected error ID if provided
            if ($expectedErrorId !== null && isset($error['id'])) {
                \PHPUnit\Framework\Assert::assertEquals($expectedErrorId, $error['id']);
            }
        }
    }

    /**
     * Assert API response has standard success envelope structure
     * 
     * @param TestResponse $response
     * @param int $expectedStatus Expected HTTP status code (default 200)
     * @return void
     */
    public static function assertSuccessEnvelope(
        TestResponse $response,
        int $expectedStatus = 200
    ): void {
        $response->assertStatus($expectedStatus);
        
        $json = $response->json();
        
        // Check for standard success envelope structure
        // Format: { status: 'success', data: {...} }
        if (isset($json['status'])) {
            \PHPUnit\Framework\Assert::assertEquals('success', $json['status']);
        } elseif (isset($json['success'])) {
            \PHPUnit\Framework\Assert::assertTrue($json['success']);
        }
    }

    /**
     * Assert response includes X-Request-Id header
     * 
     * @param TestResponse $response
     * @return string The request ID
     */
    public static function assertHasRequestId(TestResponse $response): string
    {
        $requestId = $response->headers->get('X-Request-Id');
        
        \PHPUnit\Framework\Assert::assertNotNull(
            $requestId,
            'Response should include X-Request-Id header for log correlation'
        );
        
        \PHPUnit\Framework\Assert::assertNotEmpty(
            $requestId,
            'X-Request-Id header should not be empty'
        );
        
        return $requestId;
    }

    /**
     * Assert API response has standard structure with X-Request-Id
     * 
     * @param TestResponse $response
     * @param int $expectedStatus Expected HTTP status code
     * @param bool $isSuccess Whether this is a success response
     * @return string The request ID
     */
    public static function assertStandardApiResponse(
        TestResponse $response,
        int $expectedStatus,
        bool $isSuccess = true
    ): string {
        $response->assertStatus($expectedStatus);
        
        if ($isSuccess) {
            self::assertSuccessEnvelope($response, $expectedStatus);
        } else {
            self::assertErrorEnvelope($response, $expectedStatus);
        }
        
        return self::assertHasRequestId($response);
    }

    /**
     * Assert response includes tenant context in error (if applicable)
     * 
     * @param TestResponse $response
     * @param string|null $expectedTenantId Expected tenant ID (optional)
     * @return void
     */
    public static function assertTenantContext(
        TestResponse $response,
        ?string $expectedTenantId = null
    ): void {
        $json = $response->json();
        
        // Check if error includes tenant_id (for multi-tenant context)
        if (isset($json['error']['tenant_id'])) {
            if ($expectedTenantId !== null) {
                \PHPUnit\Framework\Assert::assertEquals($expectedTenantId, $json['error']['tenant_id']);
            }
        }
    }
}

