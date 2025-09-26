<?php declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ErrorEnvelopeMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

class ErrorEnvelopeMiddlewareTest extends TestCase
{
    protected $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ErrorEnvelopeMiddleware();
    }

    /**
     * Test error envelope middleware with successful response
     */
    public function test_error_envelope_middleware_with_successful_response()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true, 'data' => ['test' => 'value']]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayNotHasKey('error', $data);
    }

    /**
     * Test error envelope middleware with error response
     */
    public function test_error_envelope_middleware_with_error_response()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Test error'], 400);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('id', $data['error']);
        $this->assertArrayHasKey('code', $data['error']);
        $this->assertArrayHasKey('message', $data['error']);
        $this->assertArrayHasKey('details', $data['error']);
    }

    /**
     * Test error envelope middleware with validation error
     */
    public function test_error_envelope_middleware_with_validation_error()
    {
        $request = Request::create('/test', 'POST');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'error' => 'Validation failed',
                'validation' => [
                    'email' => ['The email field is required.'],
                    'password' => ['The password field is required.']
                ]
            ], 422);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E422.VALIDATION', $data['error']['code']);
        $this->assertEquals('Validation failed', $data['error']['message']);
        $this->assertArrayHasKey('validation', $data['error']['details']);
    }

    /**
     * Test error envelope middleware with authentication error
     */
    public function test_error_envelope_middleware_with_authentication_error()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Unauthorized'], 401);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E401.AUTHENTICATION', $data['error']['code']);
        $this->assertEquals('Unauthorized', $data['error']['message']);
    }

    /**
     * Test error envelope middleware with authorization error
     */
    public function test_error_envelope_middleware_with_authorization_error()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Forbidden'], 403);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E403.AUTHORIZATION', $data['error']['code']);
        $this->assertEquals('Forbidden', $data['error']['message']);
    }

    /**
     * Test error envelope middleware with not found error
     */
    public function test_error_envelope_middleware_with_not_found_error()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Not found'], 404);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E404.NOT_FOUND', $data['error']['code']);
        $this->assertEquals('Not found', $data['error']['message']);
    }

    /**
     * Test error envelope middleware with server error
     */
    public function test_error_envelope_middleware_with_server_error()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Internal server error'], 500);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E500.SERVER_ERROR', $data['error']['code']);
        $this->assertEquals('Internal server error', $data['error']['message']);
    }

    /**
     * Test error envelope middleware with rate limit error
     */
    public function test_error_envelope_middleware_with_rate_limit_error()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Too many requests'], 429);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(429, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E429.RATE_LIMIT', $data['error']['code']);
        $this->assertEquals('Too many requests', $data['error']['message']);
        
        // Check Retry-After header
        $this->assertEquals('60', $response->headers->get('Retry-After'));
    }

    /**
     * Test error envelope middleware with service unavailable error
     */
    public function test_error_envelope_middleware_with_service_unavailable_error()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Service unavailable'], 503);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(503, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E503.SERVICE_UNAVAILABLE', $data['error']['code']);
        $this->assertEquals('Service unavailable', $data['error']['message']);
        
        // Check Retry-After header
        $this->assertEquals('60', $response->headers->get('Retry-After'));
    }

    /**
     * Test error envelope middleware with custom request ID
     */
    public function test_error_envelope_middleware_with_custom_request_id()
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Request-Id', 'custom-request-123');
        
        App::instance('request', $request);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Test error'], 400);
        });

        $data = $response->getData(true);
        $this->assertEquals('custom-request-123', $data['error']['id']);
    }

    /**
     * Test error envelope middleware with non-JSON response
     */
    public function test_error_envelope_middleware_with_non_json_response()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return response('HTML content', 200);
        });

        $this->assertNotInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('HTML content', $response->getContent());
    }

    /**
     * Test error envelope middleware with redirect response
     */
    public function test_error_envelope_middleware_with_redirect_response()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return redirect('/dashboard');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->headers->get('Location'));
    }

    /**
     * Test error envelope middleware with exception
     */
    public function test_error_envelope_middleware_with_exception()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            throw new \Exception('Test exception');
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E500.SERVER_ERROR', $data['error']['code']);
        $this->assertEquals('Test exception', $data['error']['message']);
    }
}
