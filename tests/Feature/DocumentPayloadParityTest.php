<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Src\DocumentManagement\Models\DocumentVersion as CanonicalDocumentVersion;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

/** @coversNothing */
class DocumentPayloadParityTest extends TestCase
{
    use RefreshDatabase;
    use RbacTestTrait;

    public function test_document_endpoints_match_structurally_between_legacy_and_canonical_modes(): void
    {
        $this->reloadApplicationForMode(false);

        $auth = $this->actingAsWithPermissions(['document.read']);
        $user = $auth['user'];
        $this->setAuthorizationHeader($user);

        $project = Project::factory()->create(['tenant_id' => $user->tenant_id]);
        $documents = $this->createDocumentsWithVersions($project, $user, 2);
        $primaryDocument = $documents->first();
        $projectId = (string) $project->id;
        $primaryDocumentId = (string) $primaryDocument->id;

        $legacyPayloads = $this->collectEndpointResponses($projectId, $primaryDocumentId);

        $this->reloadApplicationForMode(true);
        $freshUser = User::findOrFail($user->id);
        $this->setAuthorizationHeader($freshUser);

        $canonicalPayloads = $this->collectEndpointResponses($projectId, $primaryDocumentId);

        $this->assertPayloadParity($legacyPayloads, $canonicalPayloads);
    }

    /**
     * Reload the application so routes honor the requested canonical flag.
     */
    private function reloadApplicationForMode(bool $canonical): void
    {
        putenv('API_CANONICAL_DOCUMENTS=' . ($canonical ? '1' : '0'));
        $_ENV['API_CANONICAL_DOCUMENTS'] = $canonical ? '1' : '0';

        $this->refreshApplication();

        Config::set('api_migration.canonical_documents', $canonical);
    }

    /**
     * Ensure every request uses a fresh sanctum token so the guard passes.
     */
    private function setAuthorizationHeader(User $user): void
    {
        $token = $user->createToken('document-payload-parity')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function collectEndpointResponses(string $projectId, $documentId): array
    {
        $map = [];

        $response = $this->getJson('/api/documents?project_id=' . urlencode($projectId));
        $map['index'] = $response->assertOk()->json();

        $response = $this->getJson("/api/documents/{$documentId}");
        $map['show'] = $response->assertOk()->json();

        $map['versions'] = $this->getJson("/api/documents/{$documentId}/versions")
            ->assertOk()
            ->json();

        return $map;
    }

    private function createDocumentsWithVersions(Project $project, User $user, int $count): \Illuminate\Support\Collection
    {
        return collect(range(1, $count))->map(fn (int $index) => $this->createDocumentWithVersions($project, $user, $index));
    }

    private function createDocumentWithVersions(Project $project, User $user, int $index): Document
    {
        $base = Document::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $user->tenant_id,
            'uploaded_by' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => "Parity Document {$index}",
            'original_name' => "parity-document-{$index}-v2.pdf",
            'file_path' => "documents/parity-document-{$index}-v2.pdf",
            'file_size' => 6144,
            'mime_type' => 'application/pdf',
            'file_type' => 'application/pdf',
            'file_hash' => Str::random(32),
            'description' => "Parity document {$index}",
            'metadata' => ['parity' => true],
            'status' => 'active',
            'version' => 2,
            'is_current_version' => true,
            'parent_document_id' => null,
        ]);

        Document::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $user->tenant_id,
            'uploaded_by' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => "Parity Document {$index} (v1)",
            'original_name' => "parity-document-{$index}-v1.pdf",
            'file_path' => "documents/parity-document-{$index}-v1.pdf",
            'file_size' => 4096,
            'mime_type' => 'application/pdf',
            'file_type' => 'application/pdf',
            'file_hash' => Str::random(32),
            'description' => "Parity document {$index} version 1",
            'metadata' => ['parity' => 'legacy'],
            'status' => 'active',
            'version' => 1,
            'is_current_version' => false,
            'parent_document_id' => $base->id,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(9),
        ]);

        $versionOne = $this->makeCanonicalVersion($base, $user, 1, "documents/parity-document-{$index}-v1.pdf");
        $versionTwo = $this->makeCanonicalVersion($base, $user, 2, "documents/parity-document-{$index}-v2.pdf");

        $base->update([
            'current_version_id' => $versionTwo->id,
            'file_path' => $versionTwo->file_path,
            'file_size' => $versionTwo->metadata['size'] ?? 6144,
            'mime_type' => $versionTwo->metadata['mime_type'] ?? 'application/pdf',
            'file_hash' => Str::random(32),
            'version' => 2,
            'updated_by' => $user->id,
        ]);

        return $base;
    }

    private function makeCanonicalVersion(Document $document, User $user, int $number, string $path): CanonicalDocumentVersion
    {
        return CanonicalDocumentVersion::create([
            'id' => Str::ulid(),
            'document_id' => $document->id,
            'version_number' => $number,
            'file_path' => $path,
            'storage_driver' => CanonicalDocumentVersion::STORAGE_LOCAL,
            'comment' => "Parity payload version {$number}",
            'metadata' => [
                'size' => $number === 1 ? 4096 : 6144,
                'mime_type' => 'application/pdf',
                'original_filename' => basename($path),
            ],
            'created_by' => $user->id,
            'reverted_from_version_number' => null,
        ]);
    }

    private function assertPayloadParity(array $legacy, array $canonical): void
    {
        foreach (['index', 'show', 'versions'] as $endpoint) {
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
            'versions' => $this->normalizeVersionsPayload($payload),
            default => [],
        };
    }

    private function normalizeIndexPayload(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $items = $data['data'] ?? [];
        $firstItem = $items[0] ?? [];

        return [
            'top_keys' => $this->sortedKeys($payload),
            'pagination_keys' => $this->sortedKeys($data),
            'item_keys' => $this->sortedKeys($firstItem),
            'item_count' => count($items),
        ];
    }

    private function normalizeShowPayload(array $payload): array
    {
        $data = $payload['data'] ?? [];

        return [
            'top_keys' => $this->sortedKeys($payload),
            'data_keys' => $this->sortedKeys($data),
        ];
    }

    private function normalizeVersionsPayload(array $payload): array
    {
        $versions = $payload['data'] ?? [];
        $first = $versions[0] ?? [];

        return [
            'top_keys' => $this->sortedKeys($payload),
            'version_keys' => $this->sortedKeys($first),
            'version_count' => count($versions),
        ];
    }

    private function sortedKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);
        return $keys;
    }
}
