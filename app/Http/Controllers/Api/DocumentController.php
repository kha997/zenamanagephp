<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = Document::with([
                'project:id,name,status',
                'task:id,name,status',
                'component:id,name,type',
                'uploadedBy:id,name,email',
                'approvedBy:id,name,email',
                'tenant:id,name'
            ]);

            // Apply tenant filter
            if ($user->tenant_id) {
                $query->where('tenant_id', $user->tenant_id);
            }

            // Apply filters
            if ($request->filled('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->filled('task_id')) {
                $query->where('task_id', $request->input('task_id'));
            }

            if ($request->filled('component_id')) {
                $query->where('component_id', $request->input('component_id'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('category')) {
                $query->where('category', $request->input('category'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('uploaded_by')) {
                $query->where('uploaded_by', $request->input('uploaded_by'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('original_name', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = min($request->input('per_page', 15), 100);
            $documents = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $documents,
                'message' => 'Documents retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Document index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:zena_projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|in:drawing,specification,contract,report,photo,other',
            'file' => 'required|file|max:10240',
            'version' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $file = $request->file('file');
            
            // Use FileStorageService with enhanced validation
            $fileStorageService = new \Src\Foundation\Services\FileStorageService();
            $uploadResult = $fileStorageService->uploadFile(
                $file,
                'local',
                'documents/' . $request->input('project_id')
            );

            if (!$uploadResult['success']) {
                return $this->error('File upload failed: ' . $uploadResult['error'], 400);
            }

            $fileInfo = $uploadResult['file'];

            $document = ZenaDocument::create([
                'project_id' => $request->input('project_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'document_type' => $request->input('document_type'),
                'file_path' => $fileInfo['path'],
                'file_name' => $fileInfo['filename'],
                'original_name' => $fileInfo['original_name'],
                'file_size' => $fileInfo['size'],
                'mime_type' => $fileInfo['mime_type'],
                'version' => $request->input('version', '1.0'),
                'tags' => $request->input('tags', []),
                'uploaded_by' => $user->id,
                'status' => 'active',
            ]);

            return $this->successResponse($document->load(['project', 'uploadedBy']), 'Document uploaded successfully', 201);

        } catch (\Exception $e) {
            return $this->error('Failed to upload document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $document = ZenaDocument::with(['project', 'uploadedBy', 'versions'])
            ->find($id);

        if (!$document) {
            return $this->error('Document not found', 404);
        }

        return $this->successResponse($document);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $document = ZenaDocument::find($id);

        if (!$document) {
            return $this->error('Document not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'sometimes|required|in:drawing,specification,contract,report,photo,other',
            'version' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'status' => 'sometimes|required|in:active,archived,deleted',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $document->update($request->only([
                'title', 'description', 'document_type', 'version', 'tags', 'status'
            ]));

            return $this->successResponse($document->load(['project', 'uploadedBy']), 'Document updated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to update document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $document = ZenaDocument::find($id);

        if (!$document) {
            return $this->error('Document not found', 404);
        }

        try {
            // Delete file from storage
            if (Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }

            $document->delete();

            return $this->successResponse(null, 'Document deleted successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to delete document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download document
     */
    public function download(string $id): JsonResponse
    {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $document = ZenaDocument::find($id);

        if (!$document) {
            return $this->error('Document not found', 404);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            return $this->error('File not found on storage', 404);
        }

        try {
            $filePath = Storage::disk('local')->path($document->file_path);
            
            return response()->download($filePath, $document->original_name);

        } catch (\Exception $e) {
            return $this->error('Failed to download document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new version of document
     */
    public function createVersion(Request $request, string $id): JsonResponse
    {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,zip,rar,7z',
            'version' => 'required|string|max:50',
            'change_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $document = ZenaDocument::find($id);

        if (!$document) {
            return $this->error('Document not found', 404);
        }

        try {
            $file = $request->file('file');
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file);
            $path = 'documents/' . $document->project_id . '/' . $filename;
            
            // Store file
            $storedPath = Storage::disk('local')->putFileAs(
                'documents/' . $document->project_id,
                $file,
                $filename
            );

            if (!$storedPath) {
                return $this->error('Failed to store file', 500);
            }

            // Create new version
            $newVersion = ZenaDocument::create([
                'project_id' => $document->project_id,
                'title' => $document->title,
                'description' => $document->description,
                'document_type' => $document->document_type,
                'file_path' => $storedPath,
                'file_name' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'version' => $request->input('version'),
                'tags' => $document->tags,
                'uploaded_by' => $user->id,
                'status' => 'active',
                'parent_document_id' => $document->id,
                'change_notes' => $request->input('change_notes'),
            ]);

            return $this->successResponse($newVersion->load(['project', 'uploadedBy']), 'Document version created successfully', 201);

        } catch (\Exception $e) {
            return $this->error('Failed to create document version: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get document versions
     */
    public function getVersions(string $id): JsonResponse
    {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $document = ZenaDocument::find($id);

        if (!$document) {
            return $this->error('Document not found', 404);
        }

        $versions = ZenaDocument::where('parent_document_id', $id)
            ->orWhere('id', $id)
            ->with(['uploadedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($versions);
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        
        return $basename . '_' . time() . '_' . str_random(8) . '.' . $extension;
    }
}
