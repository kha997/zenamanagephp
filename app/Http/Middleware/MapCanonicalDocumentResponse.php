<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Document as LegacyDocument;
use App\Models\DocumentVersion as LegacyDocumentVersion;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MapCanonicalDocumentResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!($response instanceof JsonResponse)) {
            return $response;
        }

        $action = $request->route()?->getActionMethod();
        if (!\in_array($action, ['index', 'show', 'getVersions'], true)) {
            return $response;
        }

        $payload = $response->getData(true);
        if (($payload['status'] ?? null) !== 'success') {
            return $response;
        }

        switch ($action) {
            case 'index':
                return $this->transformIndex($response, $payload, $request);
            case 'show':
                return $this->transformShow($response, $payload);
            case 'getVersions':
                return $this->transformVersions($response, $payload, $request);
        }

        return $response;
    }

    private function transformIndex(JsonResponse $response, array $payload, Request $request): JsonResponse
    {
        $collection = $payload['data'] ?? [];
        $items = $collection['data'] ?? [];
        $meta = $collection['meta'] ?? [];

        $perPage = (int) ($meta['per_page'] ?? 1);
        $currentPage = (int) ($meta['current_page'] ?? 1);
        $count = (int) ($meta['count'] ?? count($items));
        $total = (int) ($meta['total'] ?? $count);
        $from = $count > 0 ? (($currentPage - 1) * max($perPage, 1)) + 1 : null;
        $to = $count > 0 ? ($from + $count - 1) : null;
        $totalPages = (int) ($meta['total_pages'] ?? max(1, (int) ceil($total / max($perPage, 1))));

        $legacyItems = $this->hydrateLegacyDocuments($items);

        $legacyPayload = [
            'success' => true,
            'message' => 'Documents retrieved successfully',
            'data' => [
                'current_page' => $currentPage,
                'data' => $legacyItems,
                'first_page_url' => null,
                'from' => $from,
                'last_page' => $totalPages,
                'last_page_url' => null,
                'links' => [],
                'next_page_url' => null,
                'path' => $request->url(),
                'per_page' => $perPage,
                'prev_page_url' => null,
                'to' => $to,
                'total' => $total,
            ],
        ];

        return response()->json($legacyPayload, $response->getStatusCode());
    }

    private function transformShow(JsonResponse $response, array $payload): JsonResponse
    {
        $document = $payload['data']['document'] ?? $payload['data'] ?? null;
        $legacy = null;

        if (!empty($document['id'])) {
            $model = LegacyDocument::with(['project', 'uploader', 'versions'])->find($document['id']);
            if ($model) {
                $legacy = $this->serializeDocument($model, true, false, true);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $legacy ?? $document,
        ], $response->getStatusCode());
    }

    private function transformVersions(JsonResponse $response, array $payload, Request $request): JsonResponse
    {
        $documentId = $request->route()?->parameter('document');
        $versions = [];

        if ($documentId) {
            $versions = LegacyDocument::where('parent_document_id', $documentId)
                ->orWhere('id', $documentId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (LegacyDocument $document) => $this->serializeDocument($document, false, false, false))
                ->values()
                ->all();
        }

        return response()->json([
            'status' => 'success',
            'data' => $versions,
        ], $response->getStatusCode());
    }

    private function hydrateLegacyDocuments(array $items): array
    {
        $ids = array_filter(array_column($items, 'id'));
        if (empty($ids)) {
            return [];
        }

        $documents = LegacyDocument::with(['project', 'uploader'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $legacy = [];
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            if (!$id || !isset($documents[$id])) {
                continue;
            }
            $legacy[] = $this->serializeDocument($documents[$id], false, true, true);
        }

        return $legacy;
    }

    private function serializeDocument(LegacyDocument $document, bool $includeVersions = false, bool $includeTenant = false, bool $includeProject = true): array
    {
        $payload = [
            'category' => $document->category,
            'client_approved' => $document->client_approved,
            'created_at' => $document->created_at?->toISOString(),
            'created_by' => $document->created_by,
            'current_version_id' => $document->current_version_id,
            'deleted_at' => $document->deleted_at?->toISOString(),
            'deprecated_notice' => $document->deprecated_notice ?? null,
            'description' => $document->description,
            'file_hash' => $document->file_hash,
            'file_path' => $document->file_path,
            'file_size' => $document->file_size,
            'file_type' => $document->file_type,
            'id' => $document->id,
            'is_current_version' => $document->is_current_version,
            'linked_entity_id' => $document->linked_entity_id,
            'linked_entity_type' => $document->linked_entity_type,
            'metadata' => $document->metadata,
            'mime_type' => $document->mime_type,
            'status' => $document->status,
            'name' => $document->name,
            'original_name' => $document->original_name,
            'parent_document_id' => $document->parent_document_id,
            'project_id' => $document->project_id,
            'tags' => $document->tags,
            'tenant_id' => $document->tenant_id,
            'updated_at' => $document->updated_at?->toISOString(),
            'updated_by' => $document->updated_by,
            'uploaded_by' => $document->uploaded_by,
            'uploader' => $document->uploader ? [
                'id' => $document->uploader->id,
                'name' => $document->uploader->name,
                'email' => $document->uploader->email,
            ] : null,
            'version' => $document->version,
            'visibility' => $document->visibility,
        ];

        if ($includeProject && $document->project) {
            $payload['project'] = [
                'id' => $document->project->id,
                'name' => $document->project->name,
                'status' => $document->project->status,
            ];
        }

        if ($includeTenant && $document->tenant) {
            $payload['tenant'] = [
                'id' => $document->tenant->id,
                'name' => $document->tenant->name,
            ];
        }

        if ($includeVersions) {
            $payload['versions'] = $document->versions
                ->map(fn (LegacyDocumentVersion $version) => $this->serializeVersion($version))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function serializeVersion(LegacyDocumentVersion $version): array
    {
        return [
            'id' => $version->id,
            'document_id' => $version->document_id,
            'version_number' => $version->version_number,
            'file_path' => $version->file_path,
            'storage_driver' => $version->storage_driver,
            'comment' => $version->comment,
            'metadata' => $version->metadata,
            'created_by' => $version->created_by,
            'reverted_from_version_number' => $version->reverted_from_version_number,
            'created_at' => $version->created_at?->toISOString(),
        ];
    }
}
