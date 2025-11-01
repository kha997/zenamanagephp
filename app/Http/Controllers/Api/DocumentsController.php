<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentUploadRequest;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentsController extends Controller
{
    /**
     * Display a listing of documents
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Document::where('tenant_id', $user->tenant_id);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('original_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('project_id')) {
                $query->where('project_id', $request->project_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $documents = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $documents->items(),
                'meta' => [
                    'total' => $documents->total(),
                    'per_page' => $documents->perPage(),
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created document
     */
    public function store(DocumentUploadRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = $request->file('file');
            
            DB::beginTransaction();

            // Store file and get path
            $filePath = $file->store('documents', 'local');
            
            $document = Document::create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $filePath, // Fixed: use file_path instead of file_name
                'mime_type' => $file->getMimeType(), // Fixed: use mime_type instead of file_type
                'file_size' => $file->getSize(),
                'file_hash' => hash_file('sha256', $file->getPathname()),
                'project_id' => $request->validated()['project_id'] ?? null,
                'category' => $request->validated()['category'] ?? 'general',
                'description' => $request->validated()['description'] ?? null,
                'tags' => $request->validated()['tags'] ? json_encode($request->validated()['tags']) : null,
                'status' => 'pending',
                'is_public' => $request->validated()['is_public'] ?? false,
                'requires_approval' => $request->validated()['requires_approval'] ?? false,
                'tenant_id' => $user->tenant_id,
                'uploaded_by' => $user->id,
                'created_by' => $user->id,
                'updated_by' => $user->id
            ]);

            DB::commit();

            Log::info('Document uploaded via API', [
                'document_id' => $document->id,
                'original_name' => $document->original_name,
                'file_path' => $document->file_path,
                'tenant_id' => $document->tenant_id,
                'uploaded_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $document,
                'message' => 'Document uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Document upload failed via API', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
                'uploaded_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to upload document', 500);
        }
    }

    /**
     * Display the specified document
     */
    public function show(Document $document): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($document->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Document belongs to different tenant', 403);
            }

            $document->load(['project', 'uploader']);

            return response()->json([
                'success' => true,
                'data' => $document
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified document
     */
    public function update(Request $request, Document $document): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($document->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Document belongs to different tenant', 403);
            }

            $validator = Validator::make($request->all(), [
                'category' => 'nullable|string|max:100',
                'description' => 'nullable|string',
                'tags' => 'nullable|array',
                'status' => 'nullable|in:pending,approved,rejected'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            DB::beginTransaction();

            $document->update([
                ...$validator->validated(),
                'tags' => $request->tags ? json_encode($request->tags) : $document->tags,
                'updated_by' => $user->id
            ]);

            DB::commit();

            Log::info('Document updated via API', [
                'document_id' => $document->id,
                'original_name' => $document->original_name,
                'tenant_id' => $document->tenant_id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $document,
                'message' => 'Document updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Document update failed via API', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'data' => $request->all(),
                'updated_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to update document', 500);
        }
    }

    /**
     * Remove the specified document
     */
    public function destroy(Document $document): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($document->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Document belongs to different tenant', 403);
            }

            DB::beginTransaction();

            $originalName = $document->original_name;
            $document->delete();

            DB::commit();

            Log::info('Document deleted via API', [
                'document_id' => $document->id,
                'original_name' => $originalName,
                'tenant_id' => $document->tenant_id,
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Document deletion failed via API', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'deleted_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to delete document', 500);
        }
    }

    /**
     * Get documents pending approval
     */
    public function approvals(): JsonResponse
    {
        try {
            $user = Auth::user();
            $documents = Document::where('tenant_id', $user->tenant_id)
                               ->where('status', 'pending')
                               ->with(['project', 'uploader'])
                               ->latest()
                               ->get();

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Standardized error response with error envelope
     */
    private function errorResponse(string $message, int $status = 500, $errors = null): JsonResponse
    {
        $errorId = uniqid('err_', true);
        
        $response = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'message' => $message,
                'status' => $status,
                'timestamp' => now()->toISOString()
            ]
        ];

        if ($errors) {
            $response['error']['details'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Download document
     */
    public function download(Document $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                abort(401, 'User not authenticated or tenant not found');
            }

            if ($document->tenant_id !== $user->tenant_id) {
                abort(403, 'Access denied: Document belongs to different tenant');
            }

            $filePath = storage_path('app/documents/' . $document->file_path);
            
            if (!file_exists($filePath)) {
                abort(404, 'File not found');
            }

            return response()->download($filePath, $document->original_name);
        } catch (\Exception $e) {
            Log::error('Document download error: ' . $e->getMessage());
            abort(500, 'Failed to download document');
        }
    }

    /**
     * Get document statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $stats = [
                'total_documents' => Document::where('tenant_id', $user->tenant_id)->count(),
                'pending_approval' => Document::where('tenant_id', $user->tenant_id)
                    ->where('status', 'pending')->count(),
                'approved_documents' => Document::where('tenant_id', $user->tenant_id)
                    ->where('status', 'approved')->count(),
                'rejected_documents' => Document::where('tenant_id', $user->tenant_id)
                    ->where('status', 'rejected')->count(),
                'total_size_mb' => Document::where('tenant_id', $user->tenant_id)
                    ->sum('file_size') / 1024 / 1024
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Document stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch document statistics']
            ], 500);
        }
    }
}
