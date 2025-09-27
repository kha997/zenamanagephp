<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileVersion;
use App\Services\FileManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    protected $fileService;

    public function __construct(FileManagementService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Get files list
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filters = $request->only(['type', 'category', 'project_id', 'task_id', 'search', 'date_from', 'date_to']);
            
            $files = $this->fileService->searchFiles($user, $filters);

            return response()->json([
                'success' => true,
                'data' => $files
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get files list', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve files'
                ]
            ], 500);
        }
    }

    /**
     * Upload file
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:102400', // 100MB max
                'category' => 'nullable|string',
                'project_id' => 'nullable|integer|exists:projects,id',
                'task_id' => 'nullable|integer|exists:tasks,id',
                'tags' => 'nullable|array',
                'is_public' => 'nullable|boolean'
            ]);

            $user = $request->user();
            $file = $request->file('file');
            
            $options = $request->only(['category', 'project_id', 'task_id', 'tags', 'is_public']);
            $uploadedFile = $this->fileService->uploadFile($file, $user, $options);

            return response()->json([
                'success' => true,
                'data' => $uploadedFile,
                'message' => 'File uploaded successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage()
                ]
            ], 400);
        }
    }

    /**
     * Get file details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $file = File::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->with(['user', 'project', 'task', 'versions'])
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'File not found'
                    ]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $file
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get file details', [
                'file_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve file details'
                ]
            ], 500);
        }
    }

    /**
     * Update file
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:102400',
                'change_description' => 'nullable|string|max:500'
            ]);

            $user = $request->user();
            $file = File::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'File not found'
                    ]
                ], 404);
            }

            $newFile = $request->file('file');
            $changeDescription = $request->input('change_description');
            
            $version = $this->fileService->updateFile($file, $newFile, $user, $changeDescription);

            return response()->json([
                'success' => true,
                'data' => [
                    'file' => $file->fresh(),
                    'version' => $version
                ],
                'message' => 'File updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('File update failed', [
                'file_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage()
                ]
            ], 400);
        }
    }

    /**
     * Delete file
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $file = File::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'File not found'
                    ]
                ], 404);
            }

            $deleted = $this->fileService->deleteFile($file);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'Failed to delete file'
                    ]
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'file_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to delete file'
                ]
            ], 500);
        }
    }

    /**
     * Download file
     */
    public function download(Request $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $user = $request->user();
            $file = File::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$file) {
                abort(404, 'File not found');
            }

            if (!Storage::disk($file->disk)->exists($file->path)) {
                abort(404, 'File not found on disk');
            }

            $file->incrementDownloadCount();

            return Storage::disk($file->disk)->download($file->path, $file->original_name);
        } catch (\Exception $e) {
            Log::error('File download failed', [
                'file_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to download file');
        }
    }

    /**
     * Preview file
     */
    public function preview(Request $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $user = $request->user();
            $file = File::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$file) {
                abort(404, 'File not found');
            }

            if (!$file->isPreviewable()) {
                abort(400, 'File type not previewable');
            }

            if (!Storage::disk($file->disk)->exists($file->path)) {
                abort(404, 'File not found on disk');
            }

            $file->incrementDownloadCount();

            return Storage::disk($file->disk)->response($file->path, $file->original_name);
        } catch (\Exception $e) {
            Log::error('File preview failed', [
                'file_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to preview file');
        }
    }

    /**
     * Get file versions
     */
    public function versions(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $file = File::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'File not found'
                    ]
                ], 404);
            }

            $versions = $file->versions()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $versions
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get file versions', [
                'file_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve file versions'
                ]
            ], 500);
        }
    }

    /**
     * Download specific file version
     */
    public function downloadVersion(Request $request, int $fileId, int $versionId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $user = $request->user();
            $file = File::where('id', $fileId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$file) {
                abort(404, 'File not found');
            }

            $version = FileVersion::where('id', $versionId)
                ->where('file_id', $fileId)
                ->first();

            if (!$version) {
                abort(404, 'Version not found');
            }

            if (!Storage::disk($version->disk)->exists($version->path)) {
                abort(404, 'Version file not found on disk');
            }

            return Storage::disk($version->disk)->download(
                $version->path, 
                $file->original_name . ' (v' . $version->version_number . ')'
            );
        } catch (\Exception $e) {
            Log::error('File version download failed', [
                'file_id' => $fileId,
                'version_id' => $versionId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to download file version');
        }
    }

    /**
     * Get file statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $stats = $this->fileService->getFileStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get file statistics', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve file statistics'
                ]
            ], 500);
        }
    }
}
