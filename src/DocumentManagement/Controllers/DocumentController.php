<?php declare(strict_types=1);

namespace Src\DocumentManagement\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\DocumentManagement\Models\Document;
use Src\DocumentManagement\Models\DocumentVersion;
use Src\DocumentManagement\Services\DocumentService;
use Src\DocumentManagement\Resources\DocumentResource;
use Src\DocumentManagement\Resources\DocumentCollection;
use Src\DocumentManagement\Requests\StoreDocumentRequest;
use Src\DocumentManagement\Requests\UpdateDocumentRequest;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;

/**
 * Controller xử lý các hoạt động CRUD cho Document
 * 
 * @package Src\DocumentManagement\Controllers
 */
class DocumentController
{
    public function __construct(
        private DocumentService $documentService
    ) {
        // Remove middleware from constructor to avoid AuthManager issues
        // Middleware is handled at route level
    }

    /**
     * Lấy ID người dùng hiện tại một cách an toàn
     * 
     * @param Request $request
     * @return string
     */
    private function getUserId(Request $request): string
    {
        try {
            // Sử dụng $request->user('api') thay vì Auth facade để tránh lỗi AuthManager
            return $request->user('api') ? (string) $request->user('api')->id : 'system';
        } catch (\Exception $e) {
            return 'system';
        }
    }

    /**
     * Lấy danh sách documents theo project
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');
            if (!$projectId) {
                return JSendResponse::error('Project ID is required', 400);
            }

            $filters = [
                'entity_type' => $request->get('entity_type'),
                'entity_id' => $request->get('entity_id'),
                'visibility' => $request->get('visibility'),
                'client_approved' => $request->get('client_approved'),
                'document_type' => $request->get('document_type')
            ];

            $documents = $this->documentService->getDocumentsByProject($projectId, $filters);

            return JSendResponse::success(new DocumentCollection($documents));
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to retrieve documents: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết document
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $document = $this->documentService->getDocumentById($id);
            
            if (!$document) {
                return JSendResponse::error('Document not found', 404);
            }

            return JSendResponse::success([
                'document' => new DocumentResource($document)
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to retrieve document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo document mới
     *
     * @param StoreDocumentRequest $request
     * @return JsonResponse
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        try {
            $userId = (string) $request->user('api')->id;

            $document = $this->documentService->createDocument(
                $request->validated(),
                $request->file('file'),
                $userId
            );
    
            return JSendResponse::success(new DocumentResource($document), 'Document created successfully', 201);
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to create document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin document
     *
     * @param UpdateDocumentRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(UpdateDocumentRequest $request, string $id): JsonResponse
    {
        try {
            $userId = (string) $request->user('api')->id;

            $document = $this->documentService->updateDocument(
                $id,
                $request->validated(),
                $userId
            );
    
            if (!$document) {
                return JSendResponse::error('Document not found', 404);
            }
    
            return JSendResponse::success(new DocumentResource($document), 'Document updated successfully');
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to update document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa document
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $result = $this->documentService->deleteDocument($id, $this->getUserId($request));
            
            if (!$result) {
                return JSendResponse::error('Document not found', 404);
            }

            return JSendResponse::success(null, 'Document deleted successfully');
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to delete document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo version mới cho document
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function createVersion(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB
                'comment' => 'nullable|string|max:500'
            ]);

            $file = $request->file('file');
            $tenantId = $request->user()?->tenant_id ?? 'system';
            $userId = (string) $request->user()->id;

            $debugMeta = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'document_id' => $id,
                'disk' => 'local',
                'path' => null,
                'original_name' => $file?->getClientOriginalName(),
                'file_size' => $file?->getSize(),
            ];

            $traceVersionDiagnostics = app()->environment('testing') && env('ZENA_TRACE_DOC_VERSION', false);

            $createVersion = function () use ($id, $file, $request, $userId) {
                return $this->documentService->createNewVersion(
                    $id,
                    $file,
                    $request->get('comment', ''),
                    $userId
                );
            };

            if ($traceVersionDiagnostics) {
                Log::debug('[testing] document.createVersion start', $debugMeta);

                try {
                    $version = $createVersion();
                } catch (\Throwable $e) {
                    Log::error('[testing] document.createVersion exception', [
                        'ex' => $e::class,
                        'msg' => $e->getMessage(),
                        'trace' => Str::limit($e->getTraceAsString(), 4000),
                        'context' => $debugMeta,
                    ]);

                    throw $e;
                }

                $debugMeta['path'] = $version->file_path ?? null;
                $debugMeta['storage_driver'] = $version->storage_driver ?? null;
                $debugMeta['version_id'] = $version->id ?? null;
                Log::debug('[testing] document.createVersion success', $debugMeta);
            } else {
                $version = $createVersion();
            }

            if (!$version) {
                return JSendResponse::error('Document not found', 404);
            }

            return JSendResponse::success([
                'version' => $version
            ], 'New version created successfully', 201);
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to create version: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy tất cả versions của document
     */
    public function getVersions(string $id): JsonResponse
    {
        try {
            $versions = DocumentVersion::forDocument($id)
                ->orderByDesc('version_number')
                ->get();

            if ($versions->isEmpty()) {
                $documents = Document::where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('parent_document_id', $id);
                })
                ->orderByDesc('created_at')
                ->get();

                $payload = $documents->map(fn (Document $document) => [
                    'id' => $document->id,
                    'version' => $document->version ?? 1,
                    'created_at' => $document->created_at?->toISOString(),
                ])->values()->all();
                return JSendResponse::success($payload);
            }

            $payload = $versions->map(fn (DocumentVersion $version) => [
                'id' => $version->id,
                'version' => $version->version_number,
                'created_at' => $version->created_at?->toISOString(),
            ]);
            $payload = $payload->values()->all();
            return JSendResponse::success($payload);
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to retrieve document versions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Revert document về version cũ
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function revertVersion(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'version_number' => 'required|integer|min:1',
                'comment' => 'nullable|string|max:500'
            ]);

            $version = $this->documentService->revertToVersion(
                $id,
                $request->get('version_number'),
                $request->get('comment', ''),
                $request->user()->id
            );

            if (!$version) {
                return JSendResponse::error('Document or version not found', 404);
            }

            return JSendResponse::success([
                'version' => $version
            ], 'Document reverted successfully');
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to revert document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Phê duyệt document cho client
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function approveForClient(Request $request, string $id): JsonResponse
    {
        try {
            $document = $this->documentService->approveForClient($id, $this->getUserId($request));
            
            if (!$document) {
                return JSendResponse::error('Document not found', 404);
            }

            return JSendResponse::success([
                'document' => new DocumentResource($document)
            ], 'Document approved for client successfully');
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to approve document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download file của document version
     *
     * @param string $documentId
     * @param int $versionNumber
     * @return mixed
     */
    public function downloadVersion(string $documentId, int $versionNumber = null)
    {
        try {
            return $this->documentService->downloadVersion($documentId, $versionNumber);
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to download file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thống kê documents theo project
     *
     * @param string $projectId
     * @return JsonResponse
     */
    public function getStatistics(string $projectId): JsonResponse
    {
        try {
            $stats = $this->documentService->getDocumentStatistics($projectId);

            return JSendResponse::success([
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Failed to get statistics: ' . $e->getMessage(), 500);
        }
    }
}
