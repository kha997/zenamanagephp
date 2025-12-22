<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    /**
     * Upload a document
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|string|in:technical,business,legal,other',
                'project_id' => 'nullable|string|exists:projects,id'
            ]);

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents/' . $user->tenant_id, $fileName);

            $document = Document::create([
                'name' => $validated['name'],
                'original_name' => $file->getClientOriginalName(),
                'description' => $validated['description'] ?? null,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'mime_type' => $file->getMimeType(),
                'file_hash' => hash_file('sha256', $file->getPathname()),
                'category' => $validated['category'] ?? 'other',
                'project_id' => $validated['project_id'] ?? null,
                'tenant_id' => $user->tenant_id,
                'uploaded_by' => $user->id,
                'status' => 'active'
            ]);

            // Create initial version
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_path' => $filePath,
                'comment' => 'Initial version',
                'created_by' => $user->id
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'document' => [
                        'id' => $document->id,
                        'title' => $document->name, // Map name to title for test compatibility
                        'project_id' => $document->project_id,
                        'current_version_id' => null, // Will be set after version creation
                        'created_at' => $document->created_at,
                        'updated_at' => $document->updated_at
                    ]
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Document upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to upload document']
            ], 500);
        }
    }

    /**
     * Get all documents
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $projectId = $request->get('project_id');
            $category = $request->get('category');
            $status = $request->get('status', 'active');

            $documents = Document::where('tenant_id', $user->tenant_id)
                ->when($projectId, function ($query) use ($projectId) {
                    return $query->where('project_id', $projectId);
                })
                ->when($category, function ($query) use ($category) {
                    return $query->where('category', $category);
                })
                ->when($status, function ($query) use ($status) {
                    return $query->where('status', $status);
                })
                ->with(['uploader', 'project', 'currentVersion'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'documents' => $documents->items(),
                    'pagination' => [
                        'current_page' => $documents->currentPage(),
                        'last_page' => $documents->lastPage(),
                        'per_page' => $documents->perPage(),
                        'total' => $documents->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Document index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch documents']
            ], 500);
        }
    }

    /**
     * Get document details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $document = Document::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->with(['uploader', 'project', 'versions'])
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Document not found']
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $document
            ]);

        } catch (\Exception $e) {
            Log::error('Document show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch document']
            ], 500);
        }
    }

    /**
     * Upload new version
     */
    public function uploadVersion(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $document = Document::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Document not found']
                ], 404);
            }

            $validated = $request->validate([
                'file' => 'required|file|max:10240',
                'change_description' => 'required|string|max:500'
            ]);

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents/' . $user->tenant_id, $fileName);

            // Get next version number
            $lastVersion = DocumentVersion::where('document_id', $document->id)
                ->orderBy('version_number', 'desc')
                ->first();
            
            $versionNumber = $lastVersion ? 
                $lastVersion->version_number + 1 : 1;

            // Create new version
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $versionNumber,
                'file_path' => $filePath,
                'comment' => $validated['change_description'],
                'created_by' => $user->id
            ]);

            // Update document with new file info
            $document->update([
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'mime_type' => $file->getMimeType(),
                'file_hash' => hash_file('sha256', $file->getPathname())
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'version' => [
                        'id' => $version->id,
                        'document_id' => $version->document_id,
                        'version_number' => $version->version_number,
                        'file_path' => $version->file_path,
                        'comment' => $version->comment,
                        'created_at' => $version->created_at
                    ]
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Document version upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to upload new version']
            ], 500);
        }
    }

    /**
     * Get document analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $projectId = $request->get('project_id');
            $dateRange = $request->get('date_range', '30d');

            $endDate = now();
            $startDate = match($dateRange) {
                '7d' => $endDate->copy()->subDays(7),
                '30d' => $endDate->copy()->subDays(30),
                '90d' => $endDate->copy()->subDays(90),
                '1y' => $endDate->copy()->subYear(),
                default => $endDate->copy()->subDays(30)
            };

            $analytics = [
                'total_documents' => Document::where('tenant_id', $user->tenant_id)
                    ->when($projectId, function ($query) use ($projectId) {
                        return $query->where('project_id', $projectId);
                    })
                    ->count(),
                'documents_by_category' => Document::where('tenant_id', $user->tenant_id)
                    ->when($projectId, function ($query) use ($projectId) {
                        return $query->where('project_id', $projectId);
                    })
                    ->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->get(),
                'upload_trend' => Document::where('tenant_id', $user->tenant_id)
                    ->when($projectId, function ($query) use ($projectId) {
                        return $query->where('project_id', $projectId);
                    })
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                'storage_usage' => Document::where('tenant_id', $user->tenant_id)
                    ->when($projectId, function ($query) use ($projectId) {
                        return $query->where('project_id', $projectId);
                    })
                    ->sum('file_size')
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Document analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch analytics']
            ], 500);
        }
    }

    /**
     * Revert document to previous version
     */
    public function revertVersion(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $document = Document::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Document not found']
                ], 404);
            }

            $validated = $request->validate([
                'version_number' => 'required|integer|min:1'
            ]);

            $versionNumber = $validated['version_number'];
            
            // Check if version exists
            $targetVersion = DocumentVersion::where('document_id', $document->id)
                ->where('version_number', $versionNumber)
                ->first();

            if (!$targetVersion) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Version not found']
                ], 404);
            }

            // Create new version from target version
            $newVersion = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $document->getNextVersionNumber(),
                'file_path' => $targetVersion->file_path,
                'comment' => "Reverted to version {$versionNumber}",
                'created_by' => $user->id,
                'reverted_from_version_number' => $versionNumber
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'message' => "Đã khôi phục về phiên bản {$versionNumber}"
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Document revert error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to revert document']
            ], 500);
        }
    }

    /**
     * Download document
     */
    public function download(string $id): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $document = Document::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->with('currentVersion')
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Document not found']
                ], 404);
            }

            // Get file path from current version or document
            $filePath = $document->currentVersion?->file_path ?? $document->file_path;
            $filePath = storage_path('app/' . $filePath);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'File not found']
                ], 404);
            }

            return response()->download($filePath, $document->original_name, [
                'Content-Type' => $document->file_type
            ]);

        } catch (\Exception $e) {
            Log::error('Document download error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to download document']
            ], 500);
        }
    }
}