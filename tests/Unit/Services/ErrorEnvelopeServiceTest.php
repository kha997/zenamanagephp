<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ErrorEnvelopeServiceTest extends TestCase
{
    /**
     * Test error response generation
     */
    public function test_error_response_generation()
    {
        $response = ErrorEnvelopeService::error(
            'E422.VALIDATION',
            'Validation failed',
            ['field' => 'email'],
            422
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E422.VALIDATION', $data['error']['code']);
        $this->assertEquals('Validation failed', $data['error']['message']);
        $this->assertArrayHasKey('id', $data['error']);
        $this->assertArrayHasKey('details', $data['error']);
    }

    /**
     * Test validation error response
     */
    public function test_validation_error_response()
    {
        $validationErrors = [
            'email' => ['The email field is required.'],
            'password' => ['The password field is required.']
        ];

        $response = ErrorEnvelopeService::validationError($validationErrors);

        $this->assertEquals(422, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E422.VALIDATION', $data['error']['code']);
        $this->assertEquals('Validation failed', $data['error']['message']);
        $this->assertArrayHasKey('validation', $data['error']['details']);
        $this->assertEquals($validationErrors, $data['error']['details']['validation']);
    }

    /**
     * Test authentication error response
     */
    public function test_authentication_error_response()
    {
        $response = ErrorEnvelopeService::authenticationError('Invalid credentials');

        $this->assertEquals(401, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E401.AUTHENTICATION', $data['error']['code']);
        $this->assertEquals('Invalid credentials', $data['error']['message']);
    }

    /**
     * Test authorization error response
     */
    public function test_authorization_error_response()
    {
        $response = ErrorEnvelopeService::authorizationError('Insufficient permissions');

        $this->assertEquals(403, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E403.AUTHORIZATION', $data['error']['code']);
        $this->assertEquals('Insufficient permissions', $data['error']['message']);
    }

    /**
     * Test not found error response
     */
    public function test_not_found_error_response()
    {
        $response = ErrorEnvelopeService::notFoundError('User');

        $this->assertEquals(404, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E404.NOT_FOUND', $data['error']['code']);
        $this->assertEquals('User not found', $data['error']['message']);
    }

    /**
     * Test conflict error response
     */
    public function test_conflict_error_response()
    {
        $response = ErrorEnvelopeService::conflictError(
            'Resource already exists',
            ['resource_id' => 123]
        );

        $this->assertEquals(409, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E409.CONFLICT', $data['error']['code']);
        $this->assertEquals('Resource already exists', $data['error']['message']);
        $this->assertEquals(123, $data['error']['details']['resource_id']);
    }

    /**
     * Test rate limit error response
     */
    public function test_rate_limit_error_response()
    {
        $response = ErrorEnvelopeService::rateLimitError(
            'Too many requests',
            120
        );

        $this->assertEquals(429, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E429.RATE_LIMIT', $data['error']['code']);
        $this->assertEquals('Too many requests', $data['error']['message']);
        $this->assertEquals(120, $data['error']['details']['retry_after']);
        
        // Check Retry-After header
        $this->assertEquals('60', $response->headers->get('Retry-After'));
    }

    /**
     * Test server error response
     */
    public function test_server_error_response()
    {
        $response = ErrorEnvelopeService::serverError(
            'Database connection failed',
            ['exception' => 'Connection timeout']
        );

        $this->assertEquals(500, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E500.SERVER_ERROR', $data['error']['code']);
        $this->assertEquals('Database connection failed', $data['error']['message']);
        $this->assertEquals('Connection timeout', $data['error']['details']['exception']);
    }

    /**
     * Test service unavailable error response
     */
    public function test_service_unavailable_error_response()
    {
        $response = ErrorEnvelopeService::serviceUnavailableError(
            'Service maintenance',
            300
        );

        $this->assertEquals(503, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('E503.SERVICE_UNAVAILABLE', $data['error']['code']);
        $this->assertEquals('Service maintenance', $data['error']['message']);
        $this->assertEquals(300, $data['error']['details']['retry_after']);
        
        // Check Retry-After header
        $this->assertEquals('60', $response->headers->get('Retry-After'));
    }

    /**
     * Test request ID generation
     */
    public function test_request_id_generation()
    {
        $requestId1 = ErrorEnvelopeService::getCurrentRequestId();
        $this->assertNull($requestId1); // No request context in test

        // Test with mock request
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Request-Id', 'test-request-123');
        
        App::instance('request', $request);
        
        $requestId2 = ErrorEnvelopeService::getCurrentRequestId();
        $this->assertEquals('test-request-123', $requestId2);
    }

    /**
     * Test error envelope with custom request ID
     */
    public function test_error_envelope_with_custom_request_id()
    {
        $response = ErrorEnvelopeService::error(
            'E400.BAD_REQUEST',
            'Invalid request',
            [],
            400,
            'custom-request-123'
        );

        $data = $response->getData(true);
        $this->assertEquals('custom-request-123', $data['error']['id']);
    }

    /**
     * Test error envelope without request ID
     */
    public function test_error_envelope_without_request_id()
    {
        $response = ErrorEnvelopeService::error(
            'E400.BAD_REQUEST',
            'Invalid request'
        );

        $data = $response->getData(true);
        $this->assertArrayHasKey('id', $data['error']);
        $this->assertStringStartsWith('req_', $data['error']['id']);
        $this->assertEquals(8, strlen($data['error']['id']) - 4); // req_ + 8 chars
    }
}
