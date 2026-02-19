<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Testing\TestResponse;

/**
 * Trait ApiTestTrait
 * 
 * Provides API testing utilities for JSend response format
 * Handles common API assertions and response validations
 * 
 * @package Tests\Traits
 */
trait ApiTestTrait
{
    protected function resolveApiHeaders(array $headers = []): array
    {
        $baseHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $existingHeaders = property_exists($this, 'apiHeaders') ? $this->apiHeaders : [];

        return array_merge($baseHeaders, $existingHeaders, $headers);
    }

    /**
     * Assert JSend success response
     * 
     * @param TestResponse $response
     * @param array $expectedData
     * @return void
     */
    protected function assertJSendSuccess(TestResponse $response, array $expectedData = []): void
    {
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ]);

        if (!empty($expectedData)) {
            $response->assertJson(['data' => $expectedData]);
        }
    }

    /**
     * Assert JSend error response
     * 
     * @param TestResponse $response
     * @param string $expectedMessage
     * @param int $expectedStatus
     * @return void
     */
    protected function assertJSendError(TestResponse $response, string $expectedMessage = '', int $expectedStatus = 400): void
    {
        $response->assertStatus($expectedStatus)
                ->assertJson([
                    'status' => 'error'
                ]);

        if (!empty($expectedMessage)) {
            $response->assertJson(['message' => $expectedMessage]);
        }
    }

    /**
     * Assert JSend fail response (validation errors)
     * 
     * @param TestResponse $response
     * @param array $expectedErrors
     * @return void
     */
    protected function assertJSendFail(TestResponse $response, array $expectedErrors = []): void
    {
        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'fail'
                ]);

        if (!empty($expectedErrors)) {
            $response->assertJson(['data' => $expectedErrors]);
        }
    }

    /**
     * Assert unauthorized response
     * 
     * @param TestResponse $response
     * @return void
     */
    protected function assertUnauthorized(TestResponse $response): void
    {
        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ]);
    }

    /**
     * Assert forbidden response
     * 
     * @param TestResponse $response
     * @return void
     */
    protected function assertForbidden(TestResponse $response): void
    {
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Forbidden'
                ]);
    }

    /**
     * Assert not found response
     * 
     * @param TestResponse $response
     * @return void
     */
    protected function assertNotFound(TestResponse $response): void
    {
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Not Found'
                ]);
    }

    /**
     * Assert pagination structure in response
     * 
     * @param TestResponse $response
     * @return void
     */
    protected function assertPaginationStructure(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'status',
            'data' => [
                'data',
                'current_page',
                'per_page',
                'total',
                'last_page',
                'from',
                'to'
            ]
        ]);
    }

    protected function apiGet(string $uri, array $headers = []): TestResponse
    {
        return $this->withHeaders($this->resolveApiHeaders($headers))->getJson($uri);
    }

    protected function apiPost(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeaders($this->resolveApiHeaders($headers))->postJson($uri, $data);
    }

    protected function apiPostMultipart(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $multipartHeaders = $this->resolveApiHeaders($headers);
        $multipartHeaders['Content-Type'] = 'multipart/form-data';

        return $this->withHeaders($multipartHeaders)->post($uri, $data);
    }

    protected function apiPut(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeaders($this->resolveApiHeaders($headers))->putJson($uri, $data);
    }

    protected function apiPatch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeaders($this->resolveApiHeaders($headers))->patchJson($uri, $data);
    }

    protected function apiDelete(string $uri, array $headers = []): TestResponse
    {
        return $this->withHeaders($this->resolveApiHeaders($headers))->deleteJson($uri);
    }

    /**
     * Make authenticated API request
     * 
     * @param string $method
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     */
    protected function makeApiRequest(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        $headers = array_merge($defaultHeaders, $headers);

        return $this->json($method, $uri, $data, $headers);
    }
}
