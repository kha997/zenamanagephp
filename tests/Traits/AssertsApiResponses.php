<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Testing\TestResponse;

/**
 * Trait để assert API responses theo JSend format
 * Cung cấp các helper methods để kiểm tra API response structure và content
 */
trait AssertsApiResponses
{
    /**
     * Assert response có JSend success format
     */
    protected function assertSuccessResponse(TestResponse $response, int $statusCode = 200): void
    {
        $response->assertStatus($statusCode)
                ->assertJsonStructure([
                    'status',
                    'data'
                ])
                ->assertJson([
                    'status' => 'success'
                ]);
    }

    /**
     * Assert response có JSend error format
     */
    protected function assertErrorResponse(TestResponse $response, int $statusCode = 400): void
    {
        $response->assertStatus($statusCode)
                ->assertJsonStructure([
                    'status',
                    'message'
                ])
                ->assertJson([
                    'status' => 'error'
                ]);
    }

    /**
     * Assert validation error response
     */
    protected function assertValidationError(TestResponse $response, array $fields = []): void
    {
        $response->assertStatus(422)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'validation_errors'
                    ]
                ])
                ->assertJson([
                    'status' => 'error'
                ]);

        if (!empty($fields)) {
            foreach ($fields as $field) {
                $response->assertJsonPath("data.validation_errors.{$field}", fn($value) => !empty($value));
            }
        }
    }

    /**
     * Assert unauthorized response
     */
    protected function assertUnauthorized(TestResponse $response): void
    {
        $this->assertErrorResponse($response, 401);
    }

    /**
     * Assert forbidden response
     */
    protected function assertForbidden(TestResponse $response): void
    {
        $this->assertErrorResponse($response, 403);
    }

    /**
     * Assert not found response
     */
    protected function assertNotFound(TestResponse $response): void
    {
        $this->assertErrorResponse($response, 404);
    }

    /**
     * Assert paginated response structure
     */
    protected function assertPaginatedResponse(TestResponse $response): void
    {
        $this->assertSuccessResponse($response);
        
        $response->assertJsonStructure([
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

    /**
     * Assert response contains specific data
     */
    protected function assertResponseContains(TestResponse $response, array $expectedData): void
    {
        $this->assertSuccessResponse($response);
        
        foreach ($expectedData as $key => $value) {
            $response->assertJsonPath("data.{$key}", $value);
        }
    }

    /**
     * Assert response data structure
     */
    protected function assertDataStructure(TestResponse $response, array $structure): void
    {
        $this->assertSuccessResponse($response);
        $response->assertJsonStructure(['data' => $structure]);
    }
}