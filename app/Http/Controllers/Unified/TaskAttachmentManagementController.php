<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Models\TaskAttachment;
use App\Models\TaskAttachmentVersion;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TaskAttachmentManagementController extends Controller
{
    /**
     * Get attachments for a task
     */
    public function getAttachmentsForTask(string $taskId, Request $request): JsonResponse
    {
        $filters = $request->only([
            'category',
            'is_public',
            'uploaded_by',
            'search',
            'date_from',
            'date_to',
            'per_page',
            'include_versions'
        ]);

        try {
            $query = TaskAttachment::query()
                ->where('task_id', $taskId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->active();

            // Apply filters
            if (!empty($filters['category'])) {
                $query->where('category', $filters['category']);
            }

            if (isset($filters['is_public'])) {
                $query->where('is_public', (bool) $filters['is_public']);
            }

            if (!empty($filters['uploaded_by'])) {
                $query->where('uploaded_by', $filters['uploaded_by']);
            }

            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('original_name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('description', 'like', '%' . $filters['search'] . '%');
                });
            }

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            // Include relationships
            $query->with(['uploader:id,name,email', 'currentVersion']);

            if (!empty($filters['include_versions'])) {
                $query->with('versions.uploader:id,name,email');
            }

            // Pagination
            $perPage = min((int) ($filters['per_page'] ?? 15), 100);
            $attachments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return ApiResponse::paginated(
                $attachments->items(),
                [
                    'current_page' => $attachments->currentPage(),
                    'last_page' => $attachments->lastPage(),
                    'per_page' => $attachments->perPage(),
                    'total' => $attachments->total(),
                ]
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Task not found');
        } catch (\Exception $e) {
            Log::error('Error fetching attachments for task: ' . $e->getMessage(), ['task_id' => $taskId]);
            return ApiResponse::error('Failed to fetch attachments', 'ATTACHMENT_FETCH_ERROR');
        }
    }

    /**
     * Upload attachment for a task
     */
    public function uploadAttachment(Request $request): JsonResponse
    {
        $request->validate([
            'task_id' => 'required|string|exists:tasks,id',
            'file' => 'required|file|max:51200', // 50MB max
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:' . implode(',', TaskAttachment::VALID_CATEGORIES),
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_public' => 'nullable|boolean',
            'metadata' => 'nullable|array'
        ]);

        try {
            $file = $request->file('file');
            $taskId = $request->input('task_id');

            // Verify task exists and user has access
            $task = \App\Models\Task::where('id', $taskId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->firstOrFail();

            // Check if user can upload attachments to this task
            if (!auth()->user()->can('upload-attachments', $task)) {
                throw new AuthorizationException('You do not have permission to upload attachments to this task');
            }

            // Create attachment
            $attachment = TaskAttachment::createFromUpload(
                $file,
                $taskId,
                (string) auth()->id(),
                [
                    'description' => $request->input('description'),
                    'category' => $request->input('category'),
                    'tags' => $request->input('tags'),
                    'is_public' => $request->boolean('is_public', false),
                    'metadata' => $request->input('metadata')
                ]
            );

            // Load relationships
            $attachment->load(['uploader:id,name,email', 'task:id,name']);

            Log::info('Attachment uploaded successfully', [
                'attachment_id' => $attachment->id,
                'task_id' => $taskId,
                'user_id' => auth()->id(),
                'file_name' => $attachment->original_name,
                'file_size' => $attachment->size
            ]);

            return ApiResponse::success($attachment, 'Attachment uploaded successfully');

        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Task not found');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(['file' => [$e->getMessage()]]);
        } catch (\Exception $e) {
            Log::error('Error uploading attachment: ' . $e->getMessage(), [
                'task_id' => $request->input('task_id'),
                'user_id' => auth()->id()
            ]);
            return ApiResponse::error('Failed to upload attachment', 'ATTACHMENT_UPLOAD_ERROR');
        }
    }

    /**
     * Get specific attachment
     */
    public function getAttachment(string $attachmentId): JsonResponse
    {
        try {
            $attachment = TaskAttachment::where('id', $attachmentId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->active()
                ->with(['uploader:id,name,email', 'task:id,name', 'currentVersion', 'versions.uploader:id,name,email'])
                ->firstOrFail();

            // Check if user can view this attachment
            if (!auth()->user()->can('view-attachment', $attachment)) {
                throw new AuthorizationException('You do not have permission to view this attachment');
            }

            return ApiResponse::success($attachment);

        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Attachment not found');
        } catch (\Exception $e) {
            Log::error('Error fetching attachment: ' . $e->getMessage(), ['attachment_id' => $attachmentId]);
            return ApiResponse::error('Failed to fetch attachment', 'ATTACHMENT_FETCH_ERROR');
        }
    }

    /**
     * Update attachment metadata
     */
    public function updateAttachment(string $attachmentId, Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:' . implode(',', TaskAttachment::VALID_CATEGORIES),
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_public' => 'nullable|boolean',
            'metadata' => 'nullable|array'
        ]);

        try {
            $attachment = TaskAttachment::where('id', $attachmentId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->active()
                ->firstOrFail();

            // Check if user can update this attachment
            if (!auth()->user()->can('update-attachment', $attachment)) {
                throw new AuthorizationException('You do not have permission to update this attachment');
            }

            $attachment->update($request->only([
                'description',
                'category',
                'tags',
                'is_public',
                'metadata'
            ]));

            $attachment->load(['uploader:id,name,email', 'task:id,name']);

            Log::info('Attachment updated successfully', [
                'attachment_id' => $attachmentId,
                'user_id' => auth()->id()
            ]);

            return ApiResponse::success($attachment, 'Attachment updated successfully');

        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Attachment not found');
        } catch (\Exception $e) {
            Log::error('Error updating attachment: ' . $e->getMessage(), ['attachment_id' => $attachmentId]);
            return ApiResponse::error('Failed to update attachment', 'ATTACHMENT_UPDATE_ERROR');
        }
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(string $attachmentId): JsonResponse
    {
        try {
            $attachment = TaskAttachment::where('id', $attachmentId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->active()
                ->firstOrFail();

            // Check if user can delete this attachment
            if (!auth()->user()->can('delete-attachment', $attachment)) {
                throw new AuthorizationException('You do not have permission to delete this attachment');
            }

            // Delete physical file
            $attachment->deleteFile();

            // Delete all versions
            foreach ($attachment->versions as $version) {
                $version->deleteFile();
                $version->delete();
            }

            // Soft delete attachment
            $attachment->delete();

            Log::info('Attachment deleted successfully', [
                'attachment_id' => $attachmentId,
                'user_id' => auth()->id()
            ]);

            return ApiResponse::success(null, 'Attachment deleted successfully');

        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Attachment not found');
        } catch (\Exception $e) {
            Log::error('Error deleting attachment: ' . $e->getMessage(), ['attachment_id' => $attachmentId]);
            return ApiResponse::error('Failed to delete attachment', 'ATTACHMENT_DELETE_ERROR');
        }
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(string $attachmentId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $attachment = TaskAttachment::where('id', $attachmentId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->active()
                ->firstOrFail();

            // Check if user can download this attachment
            if (!auth()->user()->can('download-attachment', $attachment)) {
                throw new AuthorizationException('You do not have permission to download this attachment');
            }

            // Check if file exists
            if (!$attachment->fileExists()) {
                throw new \Exception('File not found on storage');
            }

            // Increment download count
            $attachment->incrementDownloadCount();

            Log::info('Attachment downloaded', [
                'attachment_id' => $attachmentId,
                'user_id' => auth()->id(),
                'file_name' => $attachment->original_name
            ]);

            return Storage::disk($attachment->disk)->download(
                $attachment->file_path,
                $attachment->original_name
            );

        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, 'Attachment not found');
        } catch (\Exception $e) {
            Log::error('Error downloading attachment: ' . $e->getMessage(), ['attachment_id' => $attachmentId]);
            abort(500, 'Failed to download attachment');
        }
    }

    /**
     * Preview attachment
     */
    public function previewAttachment(string $attachmentId): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $attachment = TaskAttachment::where('id', $attachmentId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->active()
                ->firstOrFail();

            // Check if user can view this attachment
            if (!auth()->user()->can('view-attachment', $attachment)) {
                throw new AuthorizationException('You do not have permission to view this attachment');
            }

            // Check if file can be previewed
            if (!$attachment->can_preview) {
                throw new \Exception('File type cannot be previewed');
            }

            // Check if file exists
            if (!$attachment->fileExists()) {
                throw new \Exception('File not found on storage');
            }

            Log::info('Attachment previewed', [
                'attachment_id' => $attachmentId,
                'user_id' => auth()->id(),
                'file_name' => $attachment->original_name
            ]);

            return Storage::disk($attachment->disk)->response(
                $attachment->file_path,
                $attachment->original_name,
                [
                    'Content-Type' => $attachment->mime_type,
                    'Content-Disposition' => 'inline; filename="' . $attachment->original_name . '"'
                ]
            );

        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, 'Attachment not found');
        } catch (\Exception $e) {
            Log::error('Error previewing attachment: ' . $e->getMessage(), ['attachment_id' => $attachmentId]);
            abort(500, 'Failed to preview attachment');
        }
    }

    /**
     * Upload new version of attachment
     */
    public function uploadVersion(string $attachmentId, Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'change_description' => 'nullable|string|max:500'
        ]);

        try {
            $attachment = TaskAttachment::where('id', $attachmentId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->active()
                ->firstOrFail();

            // Check if user can update this attachment
            if (!auth()->user()->can('update-attachment', $attachment)) {
                throw new AuthorizationException('You do not have permission to update this attachment');
            }

            $file = $request->file('file');
            $changeDescription = $request->input('change_description');

            // Create new version
            $version = TaskAttachmentVersion::createFromUpload(
                $file,
                $attachmentId,
                (string) auth()->id(),
                $changeDescription
            );

            // Set as current version
            $version->setAsCurrent();

            // Update attachment metadata
            $attachment->update([
                'name' => $version->file_path,
                'size' => $version->size,
                'hash' => $version->hash,
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension()
            ]);

            $version->load(['uploader:id,name,email']);

            Log::info('Attachment version uploaded successfully', [
                'attachment_id' => $attachmentId,
                'version_id' => $version->id,
                'user_id' => auth()->id()
            ]);

            return ApiResponse::success($version, 'New version uploaded successfully');

        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Attachment not found');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(['file' => [$e->getMessage()]]);
        } catch (\Exception $e) {
            Log::error('Error uploading attachment version: ' . $e->getMessage(), ['attachment_id' => $attachmentId]);
            return ApiResponse::error('Failed to upload new version', 'ATTACHMENT_VERSION_UPLOAD_ERROR');
        }
    }

    /**
     * Get attachment statistics
     */
    public function getAttachmentStatistics(string $taskId): JsonResponse
    {
        try {
            $task = \App\Models\Task::where('id', $taskId)
                ->where('tenant_id', (string) auth()->user()->tenant_id)
                ->firstOrFail();

            // Check if user can view task attachments
            if (!auth()->user()->can('view-task', $task)) {
                throw new AuthorizationException('You do not have permission to view this task');
            }

            $stats = [
                'total_attachments' => TaskAttachment::where('task_id', $taskId)
                    ->where('tenant_id', (string) auth()->user()->tenant_id)
                    ->active()
                    ->count(),
                'total_size' => TaskAttachment::where('task_id', $taskId)
                    ->where('tenant_id', (string) auth()->user()->tenant_id)
                    ->active()
                    ->sum('size'),
                'categories' => TaskAttachment::where('task_id', $taskId)
                    ->where('tenant_id', (string) auth()->user()->tenant_id)
                    ->active()
                    ->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category'),
                'recent_uploads' => TaskAttachment::where('task_id', $taskId)
                    ->where('tenant_id', (string) auth()->user()->tenant_id)
                    ->active()
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'original_name', 'size', 'created_at', 'uploaded_by'])
            ];

            return ApiResponse::success($stats);

        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Task not found');
        } catch (\Exception $e) {
            Log::error('Error fetching attachment statistics: ' . $e->getMessage(), ['task_id' => $taskId]);
            return ApiResponse::error('Failed to fetch attachment statistics', 'ATTACHMENT_STATS_ERROR');
        }
    }
}
