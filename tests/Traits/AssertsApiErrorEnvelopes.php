<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Testing\TestResponse;

/**
 * Provides reusable assertions for standardized API error envelopes.
 */
trait AssertsApiErrorEnvelopes
{
    protected function assertForbiddenEnvelope(TestResponse $response, string $token = 'FORBIDDEN'): void
    {
        $this->assertApiErrorEnvelope($response, 403, $token);
    }

    protected function assertNotFoundEnvelope(TestResponse $response, string $token): void
    {
        $this->assertApiErrorEnvelope($response, 404, $token);
    }

    protected function assertValidationEnvelope(TestResponse $response, array $fields = [], string $token = 'VALIDATION_FAILED'): void
    {
        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'success' => false,
                'ok' => false,
                'code' => $token,
            ])
            ->assertJsonPath('error.id', $token)
            ->assertJsonStructure([
                'details' => [
                    'validation',
                ],
            ]);

        foreach ($fields as $field) {
            $response->assertJsonPath("details.validation.{$field}", fn($value) => !empty($value));
        }
    }

    private function assertApiErrorEnvelope(TestResponse $response, int $status, string $token): void
    {
        $response->assertStatus($status)
            ->assertJson([
                'status' => 'error',
                'success' => false,
                'ok' => false,
                'code' => $token,
                'error' => [
                    'id' => $token,
                ],
            ])
            ->assertJsonPath('error.id', $token);
    }
}
