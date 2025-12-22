<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Services\ProjectManagementService;
use App\Http\Requests\Unified\ProjectManagementRequest;
use App\Http\Requests\Unified\ProjectDocumentStoreRequest;
use App\Http\Requests\Unified\ProjectDocumentUpdateRequest;
use App\Http\Requests\Unified\ProjectDocumentVersionStoreRequest;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ProjectManagementController
 * 
 * Unified controller for all project management operations
 * Replaces multiple project controllers (Api/ProjectsController, Web/ProjectController, etc.)
 */
class ProjectManagementController extends Controller
{
    protected ProjectManagementService $projectService;

    public function __construct(ProjectManagementService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * Display projects list (Web or API)
     */
    public function index(ProjectManagementRequest $request)
    {
        $filters = $request->only(['search', 'status', 'priority', 'owner_id', 'start_date_from', 'start_date_to', 'end_date_from', 'end_date_to']);
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = (int) $request->get('per_page', 15);

        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        $projects = $this->projectService->getProjects(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection,
            $tenantId
        );

        // If API request (wants JSON)
        if ($request->wantsJson() || $request->is('api/*')) {
            if (method_exists($projects, 'items')) {
                return response()->json([
                    'success' => true,
                    'data' => $projects->items(),
                    'meta' => [
                        'current_page' => $projects->currentPage(),
                        'per_page' => $projects->perPage(),
                        'total' => $projects->total(),
                        'last_page' => $projects->lastPage(),
                    ]
                ]);
            }
            return $this->projectService->successResponse($projects);
        }

        // Web request - return view
        $stats = $this->projectService->getProjectStats();
        return view('app.projects.index', compact('projects', 'stats', 'filters'));
    }

    /**
     * Get projects (API)
     */
    public function getProjects(ProjectManagementRequest $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'priority', 'owner_id', 'start_date_from', 'start_date_to', 'end_date_from', 'end_date_to']);
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = (int) $request->get('per_page', 15);

        $projects = $this->projectService->getProjects(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection,
            (string) (Auth::user()?->tenant_id ?? '')
        );

        // Ensure consistent JSON structure for tests
        // If $projects is a paginator, extract items and metadata
        if (method_exists($projects, 'items')) {
            return response()->json([
                'success' => true,
                'data' => $projects->items(),
                'meta' => [
                    'current_page' => $projects->currentPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                    'last_page' => $projects->lastPage(),
                ]
            ]);
        }

        return $this->projectService->successResponse($projects);
    }

    /**
     * Get project by ID (API)
     */
    public function getProject(string $project): JsonResponse
    {
        // $project parameter comes from route {project}
        // Handle case where it might be a JSON string (from frontend)
        $projectId = $project;
        
        // If it's a JSON string, parse it to get the ID
        if (str_starts_with($project, '{') && str_ends_with($project, '}')) {
            $decoded = json_decode($project, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                $projectId = $decoded['id'];
            }
        }
        
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        $projectModel = $this->projectService->getProjectById($projectId, $tenantId);
        
        if (!$projectModel) {
            \Log::warning('[ProjectManagementController] Project not found', [
                'project_id' => $projectId,
                'project_param_raw' => $project,
                'project_param_type' => gettype($project),
                'tenant_id' => $tenantId,
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Project not found',
                'error' => 'PROJECT_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $projectModel,
            'message' => 'Project retrieved successfully'
        ]);
    }

    /**
     * Create project (API)
     */
    public function createProject(ProjectManagementRequest $request): JsonResponse
    {
        try {
            $project = $this->projectService->createProject($request->all(), (string) (Auth::user()?->tenant_id ?? ''));
            
            return $this->projectService->successResponse(
                $project,
                'Project created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update project (API)
     */
    public function updateProject(ProjectManagementRequest $request, string $id): JsonResponse
    {
        $project = $this->projectService->updateProject($id, $request->all(), (string) (Auth::user()?->tenant_id ?? ''));
        
        return $this->projectService->successResponse(
            $project,
            'Project updated successfully'
        );
    }

    /**
     * Restore project (API)
     */
    public function restoreProject(string $id): JsonResponse
    {
        $project = $this->projectService->restoreProject($id, (string) (Auth::user()?->tenant_id ?? ''));
        
        return $this->projectService->successResponse(
            $project,
            'Project restored successfully'
        );
    }

    /**
     * Delete project (API)
     */
    public function deleteProject(string $id): JsonResponse
    {
        try {
            $deleted = $this->projectService->deleteProject($id);
            
            if (!$deleted) {
                return $this->projectService->errorResponse('Failed to delete project', 500);
            }
            
            return $this->projectService->successResponse(
                null,
                'Project deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Bulk delete projects (API)
     */
    public function bulkDeleteProjects(ProjectManagementRequest $request): JsonResponse
    {
        try {
            $count = $this->projectService->bulkDeleteProjects($request->input('ids'));
            
            return $this->projectService->successResponse(
                ['deleted_count' => $count],
                "Successfully deleted {$count} projects"
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update project status (API)
     */
    public function updateProjectStatus(ProjectManagementRequest $request, string $id): JsonResponse
    {
        try {
            $project = $this->projectService->updateProjectStatus($id, $request->input('status'), (string) (Auth::user()?->tenant_id ?? ''));
            
            return $this->projectService->successResponse(
                $project,
                'Project status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update project progress (API)
     */
    public function updateProjectProgress(ProjectManagementRequest $request, int $id): JsonResponse
    {
        try {
            $project = $this->projectService->updateProjectProgress($id, $request->input('progress'));
            
            return $this->projectService->successResponse(
                $project,
                'Project progress updated successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Assign project to user (API)
     */
    public function assignProject(ProjectManagementRequest $request, int $id): JsonResponse
    {
        try {
            $project = $this->projectService->assignProject($id, $request->input('user_id'));
            
            return $this->projectService->successResponse(
                $project,
                'Project assigned successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get project statistics (API)
     */
    public function getProjectStats(): JsonResponse
    {
        try {
            $stats = $this->projectService->getProjectStats((string) (Auth::user()?->tenant_id ?? ''));
            
            return $this->projectService->successResponse($stats);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database errors
            return $this->projectService->errorResponse(
                'Database error occurred while fetching statistics',
                500,
                ['error_id' => 'stats_db_error_' . uniqid()]
            );
        } catch (\Exception $e) {
            // Handle other errors
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500,
                ['error_id' => 'stats_error_' . uniqid()]
            );
        }
    }

    /**
     * Get project timeline (API)
     * 
     * Returns timeline of project milestones, tasks, and events
     * 
     * @param string $id Project ID
     * @return JsonResponse Timeline data with project_id and timeline items
     * @throws \Exception On database errors or unauthorized access
     */
    public function getProjectTimeline(string $id): JsonResponse
    {
        try {
            // Check authentication
            if (!Auth::check()) {
                return $this->projectService->errorResponse('Unauthenticated', 401);
            }

            $user = Auth::user();
            $tenantId = (string) ($user->tenant_id ?? '');

            // Get timeline data from service
            $timelineData = $this->projectService->getProjectTimeline($id, $tenantId);
            
            if (!$timelineData) {
                return $this->projectService->errorResponse('Project not found', 404);
            }

            return $this->projectService->successResponse($timelineData);
            
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database errors
            return $this->projectService->errorResponse(
                'Database error occurred while fetching timeline',
                500,
                ['error_id' => 'timeline_db_error_' . uniqid()]
            );
        } catch (\Exception $e) {
            // Handle other errors
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500,
                ['error_id' => 'timeline_error_' . uniqid()]
            );
        }
    }

    /**
     * Search projects (API)
     */
    public function searchProjects(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        $projects = $this->projectService->searchProjects(
            $request->input('search'),
            $request->input('limit', 10)
        );

        return $this->projectService->successResponse($projects);
    }

    /**
     * Get recent projects (API)
     */
    public function getRecentProjects(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:20'
        ]);

        $projects = $this->projectService->getRecentProjects(
            $request->input('limit', 5)
        );

        return $this->projectService->successResponse($projects);
    }

    /**
     * Get project dashboard data (API)
     */
    public function getProjectDashboardData(): JsonResponse
    {
        $data = $this->projectService->getProjectDashboardData();
        
        return $this->projectService->successResponse($data);
    }

    /**
     * Show project details (Web)
     */
    public function show(string $id): View
    {
        $project = $this->projectService->getProjectById($id);
        
        if (!$project) {
            abort(404, 'Project not found');
        }

        return view('app.projects.show', compact('project'));
    }

    /**
     * Show create project form (Web)
     */
    public function create(): View
    {
        return view('app.projects.create');
    }

    /**
     * Show edit project form (Web)
     */
    public function edit(string $id): View
    {
        $project = $this->projectService->getProjectById($id);
        
        if (!$project) {
            abort(404, 'Project not found');
        }

        return view('app.projects.edit', compact('project'));
    }

    /**
     * Bulk archive projects
     */
    public function bulkArchiveProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|string|ulid'
            ]);

            $projectIds = $request->input('ids');
            $result = $this->projectService->bulkArchiveProjects($projectIds);

            return response()->json([
                'success' => true,
                'message' => 'Projects archived successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive projects: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Bulk export projects
     */
    public function bulkExportProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|string|ulid'
            ]);

            $projectIds = $request->input('ids');
            $result = $this->projectService->bulkExportProjects($projectIds);

            return response()->json([
                'success' => true,
                'message' => 'Projects exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export projects: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get project documents (API)
     */
    public function documents(Request $request, string $project): JsonResponse
    {
        // Handle case where it might be a JSON string (from frontend)
        $projectId = $project;
        
        // If it's a JSON string, parse it to get the ID
        if (str_starts_with($project, '{') && str_ends_with($project, '}')) {
            $decoded = json_decode($project, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                $projectId = $decoded['id'];
            }
        }
        
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Validate project exists and belongs to tenant
        $projectModel = $this->projectService->getProjectById($projectId, $tenantId);
        
        if (!$projectModel) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found',
                'error' => 'PROJECT_NOT_FOUND'
            ], 404);
        }
        
        // Get documents from service
        $filters = $request->only(['category', 'status', 'search']);
        $documents = $this->projectService->getProjectDocuments($projectId, $tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $documents,
            'message' => 'Project documents retrieved successfully'
        ]);
    }

    /**
     * Store project document (API)
     */
    public function storeDocument(ProjectDocumentStoreRequest $request, string $project): JsonResponse
    {
        try {
            // Handle case where it might be a JSON string (from frontend)
            $projectId = $project;
            
            // If it's a JSON string, parse it to get the ID
            if (str_starts_with($project, '{') && str_ends_with($project, '}')) {
                $decoded = json_decode($project, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $tenantId = (string) (Auth::user()?->tenant_id ?? '');
            
            // Validate project exists and belongs to tenant
            $projectModel = $this->projectService->getProjectById($projectId, $tenantId);
            
            if (!$projectModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'PROJECT_NOT_FOUND'
                ], 404);
            }
            
            // Get file and metadata
            $file = $request->file('file');
            $payload = $request->only(['name', 'description', 'category', 'status']);
            
            // Create document via service
            $document = $this->projectService->createProjectDocument(
                $projectId,
                $tenantId,
                $file,
                $payload
            );
            
            // Format response to match GET documents structure
            $documentData = [
                'id' => $document->id,
                'name' => $document->name,
                'title' => $document->name,
                'description' => $document->description,
                'category' => $document->category,
                'status' => $document->status,
                'file_type' => $document->file_type,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'file_path' => $document->file_path,
                'uploaded_by' => $document->uploader ? [
                    'id' => $document->uploader->id,
                    'name' => $document->uploader->name,
                    'email' => $document->uploader->email,
                ] : null,
                'created_at' => $document->created_at?->toISOString(),
                'updated_at' => $document->updated_at?->toISOString(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $documentData,
                'message' => 'Project document created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage(),
                'error' => 'DOCUMENT_UPLOAD_FAILED'
            ], 500);
        }
    }

    /**
     * Update project document metadata (API)
     */
    public function updateDocument(ProjectDocumentUpdateRequest $request, string $proj, string $doc): JsonResponse
    {
        try {
            // Handle case where it might be a JSON string (from frontend)
            $projectId = $proj;
            $documentId = $doc;
            
            // If it's a JSON string, parse it to get the ID
            if (str_starts_with($projectId, '{') && str_ends_with($projectId, '}')) {
                $decoded = json_decode($projectId, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $tenantId = (string) (Auth::user()?->tenant_id ?? '');
            
            // Update document via service
            $document = $this->projectService->updateProjectDocument(
                $projectId,
                $tenantId,
                $documentId,
                $request->validated()
            );
            
            // Format response to match GET documents structure
            $documentData = [
                'id' => $document->id,
                'name' => $document->name,
                'title' => $document->name,
                'description' => $document->description,
                'category' => $document->category,
                'status' => $document->status,
                'file_type' => $document->file_type,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'file_path' => $document->file_path,
                'uploaded_by' => $document->uploader ? [
                    'id' => $document->uploader->id,
                    'name' => $document->uploader->name,
                    'email' => $document->uploader->email,
                ] : null,
                'created_at' => $document->created_at?->toISOString(),
                'updated_at' => $document->updated_at?->toISOString(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $documentData,
                'message' => 'Project document updated successfully.'
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 
                ? $e->getCode() 
                : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $statusCode === 404 ? 'DOCUMENT_NOT_FOUND' : 'DOCUMENT_UPDATE_FAILED'
            ], $statusCode);
        }
    }

    /**
     * Delete project document (API)
     */
    public function destroyDocument(Request $request, string $proj, string $doc): JsonResponse
    {
        try {
            // Handle case where it might be a JSON string (from frontend)
            $projectId = $proj;
            $documentId = $doc;
            
            // If it's a JSON string, parse it to get the ID
            if (str_starts_with($projectId, '{') && str_ends_with($projectId, '}')) {
                $decoded = json_decode($projectId, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $tenantId = (string) (Auth::user()?->tenant_id ?? '');
            
            // Delete document via service
            $deleted = $this->projectService->deleteProjectDocument(
                $projectId,
                $tenantId,
                $documentId
            );
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete document',
                    'error' => 'DOCUMENT_DELETE_FAILED'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Project document deleted successfully.'
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 
                ? $e->getCode() 
                : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $statusCode === 404 ? 'DOCUMENT_NOT_FOUND' : 'DOCUMENT_DELETE_FAILED'
            ], $statusCode);
        }
    }

    /**
     * Get project history (API)
     */
    public function history(Request $request, string $project): JsonResponse
    {
        // Handle case where it might be a JSON string (from frontend)
        $projectId = $project;
        
        // If it's a JSON string, parse it to get the ID
        if (str_starts_with($project, '{') && str_ends_with($project, '}')) {
            $decoded = json_decode($project, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                $projectId = $decoded['id'];
            }
        }
        
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Validate project exists and belongs to tenant
        $projectModel = $this->projectService->getProjectById($projectId, $tenantId);
        
        if (!$projectModel) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found',
                'error' => 'PROJECT_NOT_FOUND'
            ], 404);
        }
        
        // Get history from service
        // Round 231: Added entity_id filter for cost workflow timeline
        $filters = $request->only(['action', 'entity_type', 'entity_id', 'limit']);
        $history = $this->projectService->getProjectHistory($projectId, $tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $history,
            'message' => 'Project history retrieved successfully'
        ]);
    }

    /**
     * Download project document (API)
     * 
     * Main authenticated download endpoint that either:
     * - Streams file directly for small files (<= threshold)
     * - Returns signed URL JSON for large files (> threshold)
     * 
     * @param Request $request
     * @param string $proj Project ID (raw string, no model binding)
     * @param string $doc Document ID (raw string, no model binding)
     * @return StreamedResponse|BinaryFileResponse|JsonResponse
     */
    public function downloadDocument(Request $request, string $proj, string $doc): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        try {
            // Map route parameters to internal variables for clarity
            $projectId = $proj;
            $documentId = $doc;
            
            // Handle case where it might be a JSON string (from frontend)
            if (str_starts_with($projectId, '{') && str_ends_with($projectId, '}')) {
                $decoded = json_decode($projectId, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'error' => 'UNAUTHENTICATED'
                ], 401);
            }
            
            // Ensure tenant_id is a string for consistent comparison
            $tenantId = $user->tenant_id ? (string) $user->tenant_id : '';
            
            // Find and validate document
            $documentModel = $this->projectService->findProjectDocumentForTenant(
                $projectId,
                $tenantId,
                $documentId
            );
            
            if (!$documentModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                    'error' => 'DOCUMENT_NOT_FOUND'
                ], 404);
            }
            
            // Log activity for document download
            try {
                \App\Models\ProjectActivity::logDocumentDownloaded($documentModel, $user->id);
            } catch (\Exception $e) {
                // Logging failure should not break the operation
                \Log::warning('Failed to log document download activity', [
                    'error' => $e->getMessage(),
                    'document_id' => $documentId,
                    'project_id' => $projectId,
                    'user_id' => $user->id
                ]);
            }
            
            // Check file size against threshold
            $fileSize = $documentModel->file_size ?? 0;
            $threshold = ProjectManagementService::LARGE_FILE_THRESHOLD_BYTES;
            
            if ($fileSize <= $threshold) {
                // Small file: stream directly
                try {
                    return $this->projectService->streamDocumentFile($documentModel);
                } catch (\Exception $e) {
                    \Log::error('Failed to stream document file', [
                        'document_id' => $documentModel->id,
                        'error' => $e->getMessage(),
                        'file_path' => $documentModel->file_path,
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'File not found',
                        'error' => 'FILE_NOT_FOUND'
                    ], 404);
                }
            } else {
                // Large file: generate signed URL
                $expiresAt = now()->addMinutes(15);
                $signedUrl = $this->projectService->createSignedDocumentDownloadUrl(
                    $documentModel,
                    $expiresAt
                );
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'signed_url' => $signedUrl,
                        'expires_at' => $expiresAt->toISOString(),
                        'mode' => 'signed_url'
                    ],
                    'message' => 'Signed download URL generated successfully.'
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Document download failed', [
                'project_id' => $project ?? null,
                'document_id' => $document ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download document: ' . $e->getMessage(),
                'error' => 'DOWNLOAD_FAILED'
            ], 500);
        }
    }

    /**
     * Download document via signed URL (API)
     * 
     * Protected by signed middleware - no auth required
     * The URL signature and expiry serve as protection
     * 
     * @param Request $request
     * @param string $doc Document ID (raw string, no model binding)
     * @return StreamedResponse|BinaryFileResponse|JsonResponse
     */
    public function downloadDocumentSigned(Request $request, string $doc): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        try {
            // Map route parameter to internal variable for clarity
            $documentId = $doc;
            
            // Find document by ID (no tenant check - signed URL is the gate)
            $documentModel = Document::find($documentId);
            
            if (!$documentModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                    'error' => 'DOCUMENT_NOT_FOUND'
                ], 404);
            }
            
            // Check if file exists on disk
            try {
                return $this->projectService->streamDocumentFile($documentModel);
            } catch (\Exception $e) {
                \Log::error('Failed to stream document file from signed URL', [
                    'document_id' => $documentId,
                    'error' => $e->getMessage(),
                    'file_path' => $documentModel->file_path ?? null,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                    'error' => 'FILE_NOT_FOUND'
                ], 404);
            }
            
        } catch (\Exception $e) {
            \Log::error('Signed document download failed', [
                'document_id' => $document ?? null,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download document',
                'error' => 'DOWNLOAD_FAILED'
            ], 500);
        }
    }

    /**
     * List document versions (API)
     */
    public function listDocumentVersions(Request $request, string $proj, string $doc): JsonResponse
    {
        try {
            // Handle case where it might be a JSON string (from frontend)
            $projectId = $proj;
            $documentId = $doc;
            
            // If it's a JSON string, parse it to get the ID
            if (str_starts_with($projectId, '{') && str_ends_with($projectId, '}')) {
                $decoded = json_decode($projectId, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $tenantId = (string) (Auth::user()?->tenant_id ?? '');
            
            // Get versions from service
            $versions = $this->projectService->getDocumentVersions(
                $projectId,
                $tenantId,
                $documentId
            );
            
            return response()->json([
                'success' => true,
                'data' => $versions,
                'message' => 'Document versions retrieved successfully.'
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 
                ? $e->getCode() 
                : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $statusCode === 404 ? 'DOCUMENT_NOT_FOUND' : 'VERSIONS_RETRIEVAL_FAILED'
            ], $statusCode);
        }
    }

    /**
     * Download specific document version (API)
     * 
     * Round 187: Document Versioning (View & Download Version)
     */
    public function downloadDocumentVersion(Request $request, string $proj, string $doc, string $version): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        try {
            // Handle case where it might be a JSON string (from frontend)
            $projectId = $proj;
            $documentId = $doc;
            $versionId = $version;
            
            // If it's a JSON string, parse it to get the ID
            if (str_starts_with($projectId, '{') && str_ends_with($projectId, '}')) {
                $decoded = json_decode($projectId, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $tenantId = (string) (Auth::user()?->tenant_id ?? '');
            
            // Find document with tenant and project validation
            $document = $this->projectService->findProjectDocumentForTenant($projectId, $tenantId, $documentId);
            
            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                    'error' => 'DOCUMENT_NOT_FOUND'
                ], 404);
            }
            
            // Find version that belongs to this document
            $versionModel = \App\Models\ProjectDocumentVersion::where('document_id', $document->id)
                ->where('id', $versionId)
                ->first();
            
            if (!$versionModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Version not found',
                    'error' => 'VERSION_NOT_FOUND'
                ], 404);
            }
            
            // Check if file exists on disk
            try {
                return $this->projectService->streamVersionFile($versionModel);
            } catch (\Exception $e) {
                \Log::error('Failed to stream version file', [
                    'version_id' => $versionId,
                    'document_id' => $documentId,
                    'error' => $e->getMessage(),
                    'file_path' => $versionModel->file_path ?? null,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                    'error' => 'FILE_NOT_FOUND'
                ], 404);
            }
            
        } catch (\Exception $e) {
            \Log::error('Version download failed', [
                'project_id' => $proj ?? null,
                'document_id' => $doc ?? null,
                'version_id' => $version ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download version: ' . $e->getMessage(),
                'error' => 'DOWNLOAD_FAILED'
            ], 500);
        }
    }

    /**
     * Store new document version (API)
     */
    public function storeDocumentVersion(ProjectDocumentVersionStoreRequest $request, string $proj, string $doc): JsonResponse
    {
        try {
            // Handle case where it might be a JSON string (from frontend)
            $projectId = $proj;
            $documentId = $doc;
            
            // If it's a JSON string, parse it to get the ID
            if (str_starts_with($projectId, '{') && str_ends_with($projectId, '}')) {
                $decoded = json_decode($projectId, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $tenantId = (string) (Auth::user()?->tenant_id ?? '');
            
            // Validate project exists and belongs to tenant
            $projectModel = $this->projectService->getProjectById($projectId, $tenantId);
            
            if (!$projectModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'PROJECT_NOT_FOUND'
                ], 404);
            }
            
            // Get file and metadata
            $file = $request->file('file');
            $payload = $request->only(['name', 'description', 'category', 'status']);
            
            // Upload new version via service
            $document = $this->projectService->uploadDocumentNewVersion(
                $projectId,
                $tenantId,
                $documentId,
                $file,
                $payload
            );
            
            // Format response to match GET documents structure
            $documentData = [
                'id' => $document->id,
                'name' => $document->name,
                'title' => $document->name,
                'description' => $document->description,
                'category' => $document->category,
                'status' => $document->status,
                'file_type' => $document->file_type,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'file_path' => $document->file_path,
                'uploaded_by' => $document->uploader ? [
                    'id' => $document->uploader->id,
                    'name' => $document->uploader->name,
                    'email' => $document->uploader->email,
                ] : null,
                'created_at' => $document->created_at?->toISOString(),
                'updated_at' => $document->updated_at?->toISOString(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $documentData,
                'message' => 'New document version uploaded successfully.'
            ], 201);
            
        } catch (\Exception $e) {
            $statusCode = $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 
                ? $e->getCode() 
                : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $statusCode === 404 ? 'DOCUMENT_NOT_FOUND' : 'VERSION_UPLOAD_FAILED'
            ], $statusCode);
        }
    }

    /**
     * Restore document to a specific version (API)
     * 
     * Round 189: Restore Document Version
     */
    public function restoreDocumentVersion(Request $request, string $proj, string $doc, string $version): JsonResponse
    {
        try {
            // Handle case where it might be a JSON string (from frontend)
            $projectId = $proj;
            $documentId = $doc;
            $versionId = $version;
            
            // If it's a JSON string, parse it to get the ID
            if (str_starts_with($projectId, '{') && str_ends_with($projectId, '}')) {
                $decoded = json_decode($projectId, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    $projectId = $decoded['id'];
                }
            }
            
            $tenantId = (string) (Auth::user()?->tenant_id ?? '');
            $userId = optional($request->user())->id;
            
            // Delegate to service
            $document = $this->projectService->restoreDocumentVersion(
                $projectId,
                $tenantId,
                $documentId,
                $versionId,
                $userId
            );
            
            // Format response to match GET documents structure
            $documentData = [
                'id' => $document->id,
                'name' => $document->name,
                'title' => $document->name,
                'description' => $document->description,
                'category' => $document->category,
                'status' => $document->status,
                'file_type' => $document->file_type,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'file_path' => $document->file_path,
                'uploaded_by' => $document->uploader ? [
                    'id' => $document->uploader->id,
                    'name' => $document->uploader->name,
                    'email' => $document->uploader->email,
                ] : null,
                'created_at' => $document->created_at?->toISOString(),
                'updated_at' => $document->updated_at?->toISOString(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $documentData,
                'message' => 'Document restored to selected version successfully.',
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 
                ? $e->getCode() 
                : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $statusCode === 404 ? 'VERSION_NOT_FOUND' : 'RESTORE_FAILED'
            ], $statusCode);
        }
    }
}
