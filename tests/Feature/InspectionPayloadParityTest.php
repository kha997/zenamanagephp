<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

/** @coversNothing */
final class InspectionPayloadParityTest extends TestCase
{
    use RefreshDatabase;
    use RbacTestTrait;

    public function test_inspection_endpoints_match_structurally_between_legacy_and_canonical_modes(): void
    {
        $this->reloadApplicationForMode(false);

        $auth = $this->actingAsWithPermissions(['inspection.read']);
        $user = $auth['user'];
        $this->setAuthorizationHeader($user);

        $inspections = $this->createInspections($user, 2);
        $primaryInspectionId = (string) $inspections->first()?->id;

        $legacyPayloads = $this->collectEndpointResponses('api/zena', $primaryInspectionId);

        $this->reloadApplicationForMode(true);
        $freshUser = User::findOrFail($user->id);
        $this->setAuthorizationHeader($freshUser);

        $canonicalPayloads = $this->collectEndpointResponses('api/v1', $primaryInspectionId);

        $this->assertPayloadParity($legacyPayloads, $canonicalPayloads);
    }

    private function reloadApplicationForMode(bool $canonical): void
    {
        $value = $canonical ? '1' : '0';
        putenv("API_CANONICAL_INSPECTIONS={$value}");
        $_ENV['API_CANONICAL_INSPECTIONS'] = $value;

        $this->refreshApplication();

        Config::set('api_migration.canonical_inspections', $canonical);
    }

    private function setAuthorizationHeader(User $user): void
    {
        $token = $user->createToken('inspection-payload-parity')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function collectEndpointResponses(string $prefix, string $inspectionId): array
    {
        $map = [];

        $map['index'] = $this->getJson("/{$prefix}/inspections")->assertOk()->json();
        $map['show'] = $this->getJson("/{$prefix}/inspections/{$inspectionId}")->assertOk()->json();

        return $map;
    }

    private function createInspections(User $user, int $count): Collection
    {
        $plan = QcPlan::factory()->create([
            'tenant_id' => $user->tenant_id,
        ]);

        $inspector = User::factory()->create([
            'tenant_id' => $user->tenant_id,
        ]);

        return QcInspection::factory()
            ->count($count)
            ->create([
                'tenant_id' => $user->tenant_id,
                'qc_plan_id' => $plan->id,
                'inspector_id' => $inspector->id,
            ]);
    }

    private function assertPayloadParity(array $legacy, array $canonical): void
    {
        foreach (['index', 'show'] as $endpoint) {
            $legacyNormalized = $this->normalizePayload($endpoint, $legacy[$endpoint]);
            $canonicalNormalized = $this->normalizePayload($endpoint, $canonical[$endpoint]);

            $this->assertSame(
                $legacyNormalized,
                $canonicalNormalized,
                "Payload structure for {$endpoint} diverges between legacy and canonical modes."
            );
        }
    }

    private function normalizePayload(string $endpoint, array $payload): array
    {
        return match ($endpoint) {
            'index' => $this->normalizeIndexPayload($payload),
            'show' => $this->normalizeShowPayload($payload),
            default => [],
        };
    }

    private function normalizeIndexPayload(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $meta = $payload['meta'] ?? [];
        $inspections = $data['inspections'] ?? [];
        $firstInspection = $inspections[0] ?? [];

        return [
            'top_keys' => $this->sortedKeys($payload),
            'data_keys' => $this->sortedKeys($data),
            'meta_keys' => $this->sortedKeys($meta),
            'inspection_keys' => $this->sortedKeys($firstInspection),
            'inspection_count' => count($inspections),
        ];
    }

    private function normalizeShowPayload(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $inspection = $data['inspection'] ?? [];

        return [
            'top_keys' => $this->sortedKeys($payload),
            'data_keys' => $this->sortedKeys($data),
            'inspection_keys' => $this->sortedKeys($inspection),
        ];
    }

    private function sortedKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);
        return $keys;
    }
}
