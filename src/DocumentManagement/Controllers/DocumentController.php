<?php declare(strict_types=1);

namespace Src\DocumentManagement\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Src\DocumentManagement\Models\Document;
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
                'client_approved' => $request->get('client_approved')
            ];

            $documents = $this->documentService->getDocumentsByProject($projectId, $filters);

            return JSendResponse::success([
                'documents' => new DocumentCollection($documents)
            ]);
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
            $document = $this->documentService->createDocument(
                $request->validated(),
                $request->file('file'),
                $request->user('api')->id  // Sửa từ $request->user()->id
            );
    
            return JSendResponse::success([
                'document' => new DocumentResource($document)
            ], 'Document created successfully', 201);
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
            $document = $this->documentService->updateDocument(
                $id,
                $request->validated(),
                $request->user('api')->id  // Sửa từ $request->user()->id
            );
    
            if (!$document) {
                return JSendResponse::error('Document not found', 404);
            }
    
            return JSendResponse::success([
                'document' => new DocumentResource($document)
            ], 'Document updated successfully');
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

            $version = $this->documentService->createNewVersion(
                $id,
                $request->file('file'),
                $request->get('comment', ''),
                $request->user()->id
            );

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
            $payload = $this->documentService->downloadVersion($documentId, $versionNumber);

            if ($payload['is_local']) {
                return response()->download(
                    $payload['absolute_path'],
                    $payload['filename']
                );
            }

            return response()->redirectTo($payload['url']);
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
