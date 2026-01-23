<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_endpoint_emits_telemetry(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $logger = Log::fake();
        config()->set('api_migration.log_legacy_traffic', true);
        config()->set('api_migration.log_sample_rate', 1.0);

        $response = $this->get('/api/projects');

        $response->assertStatus(200);

        $legacyRecord = null;
        foreach ($logger->records() as $record) {
            if ($record['message'] === 'api.legacy_traffic') {
                $legacyRecord = $record;
                break;
            }
        }

        $this->assertNotNull($legacyRecord, 'Legacy traffic log entry is missing');

        /** @var array{path:string,method:string,status_code:int} $context */
        $context = $legacyRecord['context'];

        $this->assertSame('/api/projects', $context['path']);
        $this->assertSame('GET', $context['method']);
        $this->assertSame(200, $context['status_code']);

        $logger->clear();

        $canonicalResponse = $this->get('/api/v1/projects');
        $canonicalResponse->assertStatus(200);

        foreach ($logger->records() as $record) {
            if ($record['message'] === 'api.legacy_traffic') {
                $this->fail('Canonical endpoint should not emit legacy telemetry');
            }
        }
    }
}
