<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Document Management Controller
 * Handles document upload, management, and organization
 */
class DocumentManagementController extends Controller
{
    /**
     * Upload document for task
     */
    public function uploadTaskDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer|exists:tasks,id',
            'file' => 'required|file|max:10240', // 10MB max
            'note' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $task = Task::findOrFail($request->task_id);
            $file = $request->file('file');
            
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
            
            // Store file
            $filePath = $file->storeAs('documents/tasks/' . $task->id, $filename, 'public');
            
            // Create document record (you would have a Document model)
            $documentData = [
                'task_id' => $task->id,
                'filename' => $filename,
                'original_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'note' => $request->note,
                'category' => $request->category ?? 'general',
                'uploaded_by' => Auth::id() ?? 1, // Default user for demo
                'uploaded_at' => now()
            ];

            // For now, store in a simple way (in production, 
            $documents[] = $documentData;
            $this->saveTaskDocuments($task->id, $documents);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'document' => $documentData,
                    'download_url' => route('documents.download', ['filename' => $filename])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload document for project
     */
    public function uploadProjectDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id',
            'file' => 'required|file|max:10240', // 10MB max
            'note' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $project = Project::findOrFail($request->project_id);
            $file = $request->file('file');
            
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
            
            // Store file
            $filePath = $file->storeAs('documents/projects/' . $project->id, $filename, 'public');
            
            // Create document record
            $documentData = [
                'project_id' => $project->id,
                'filename' => $filename,
                'original_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'note' => $request->note,
                'category' => $request->category ?? 'general',
                'uploaded_by' => Auth::id() ?? 1,
                'uploaded_at' => now()
            ];

            // Store document data
            $documents = $this->getProjectDocuments($project->id);
            $documents[] = $documentData;
            $this->saveProjectDocuments($project->id, $documents);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'document' => $documentData,
                    'download_url' => route('documents.download', ['filename' => $filename])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task documents
     */
    public function getTaskDocuments(Request $request, string $taskId): JsonResponse
    {
        try {
            $task = Task::findOrFail($taskId);
            $documents = $this->getTaskDocumentsFromStorage($taskId);

            return response()->json([
                'success' => true,
                'data' => [
                    'task' => [
                        'id' => $task->id,
                        'name' => $task->name
                    ],
                    'documents' => $documents
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get task documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project documents
     */
    public function getProjectDocuments(Request $request, string $projectId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            $documents = $this->getProjectDocuments($projectId);

            return response()->json([
                'success' => true,
                'data' => [
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->name
                    ],
                    'documents' => $documents
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get project documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete document
     */
    public function deleteDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string',
            'type' => 'required|in:task,project',
            'entity_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filename = $request->filename;
            $type = $request->type;
            $entityId = $request->entity_id;

            // Delete file from storage
            $filePath = "documents/{$type}s/{$entityId}/{$filename}";
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            // Remove from documents list
            if ($type === 'task') {
                $documents = $this->getTaskDocumentsFromStorage($entityId);
                $documents = array_filter($documents, function($doc) use ($documentId) {
                    return $doc['id'] !== $documentId;
                });
                $this->saveTaskDocuments($entityId, array_values($documents));
            } else {
                $documents = $this->getProjectDocumentsFromStorage($entityId);
                $documents = array_filter($documents, function($doc) use ($documentId) {
                    return $doc['id'] !== $documentId;
                });
                $this->saveProjectDocuments($entityId, array_values($documents));
            }

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download document
     */
    public function downloadDocument(Request $request, string $filename)
    {
        try {
            // Find the document in all possible locations
            $possiblePaths = [
                'documents/tasks/*/' . $filename,
                'documents/projects/*/' . $filename
            ];

            $filePath = null;
            foreach ($possiblePaths as $pattern) {
                $files = Storage::disk('public')->glob($pattern);
                if (!empty($files)) {
                    $filePath = $files[0];
                    break;
                }
            }

            if (!$filePath || !Storage::disk('public')->exists($filePath)) {
                abort(404, 'Document not found');
            }

            return Storage::disk('public')->download($filePath, $filename);

        } catch (\Exception $e) {
            abort(500, 'Failed to download document: ' . $e->getMessage());
        }
    }

    /**
     * Get document categories
     */
    public function getDocumentCategories(): JsonResponse
    {
        $categories = [
            'general' => 'General Documents',
            'contract' => 'Contracts & Agreements',
            'design' => 'Design Files',
            'specification' => 'Specifications',
            'report' => 'Reports',
            'invoice' => 'Invoices & Billing',
            'correspondence' => 'Correspondence',
            'meeting' => 'Meeting Notes',
            'photo' => 'Photos & Images',
            'other' => 'Other'
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Helper method to get task documents from storage
     */
    private function getTaskDocumentsFromStorage(int $taskId): array
    {
        $filePath = "documents/tasks/{$taskId}/documents.json";
        
        if (Storage::disk('public')->exists($filePath)) {
            $content = Storage::disk('public')->get($filePath);
            return json_decode($content, true) ?? [];
        }
        
        return [];
    }

    /**
     * Helper method to save task documents to storage
     */
    private function saveTaskDocuments(int $taskId, array $documents): void
    {
        $filePath = "documents/tasks/{$taskId}/documents.json";
        Storage::disk('public')->put($filePath, json_encode($documents, JSON_PRETTY_PRINT));
    }

    /**
     * Helper method to get project documents from storage
     */
    private function getProjectDocumentsFromStorage(int $projectId): array
    {
        $filePath = "documents/projects/{$projectId}/documents.json";
        
        if (Storage::disk('public')->exists($filePath)) {
            $content = Storage::disk('public')->get($filePath);
            return json_decode($content, true) ?? [];
        }
        
        return [];
    }

    /**
     * Helper method to save project documents to storage
     */
    private function saveProjectDocuments(int $projectId, array $documents): void
    {
        $filePath = "documents/projects/{$projectId}/documents.json";
        Storage::disk('public')->put($filePath, json_encode($documents, JSON_PRETTY_PRINT));
    }
}