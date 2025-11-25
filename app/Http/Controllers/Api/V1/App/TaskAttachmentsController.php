<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Task Attachments API Controller (V1)
 * 
 * Pure API controller for task attachment operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified TaskAttachmentManagementController for API routes.
 */
class TaskAttachmentsController extends BaseApiV1Controller
{
    /**
     * Get attachments for a task
     * 
     * @param string $taskId
     * @param Request $request
     * @return JsonResponse
     */
    public function getAttachmentsForTask(string $taskId, Request $request): JsonResponse
    {
        try {
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

            $tenantId = $this->getTenantId();

            $query = TaskAttachment::query()
                ->where('task_id', $taskId)
                ->where('tenant_id', $tenantId)
                ->active();

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

            $query->with(['uploader:id,name,email', 'currentVersion']);

            if (!empty($filters['include_versions'])) {
                $query->with('versions.uploader:id,name,email');
            }

            $perPage = min((int) ($filters['per_page'] ?? 15), 100);
            $attachments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            if (method_exists($attachments, 'items')) {
                return $this->paginatedResponse(
                    $attachments->items(),
                    [
                        'current_page' => $attachments->currentPage(),
                        'last_page' => $attachments->lastPage(),
                        'per_page' => $attachments->perPage(),
                        'total' => $attachments->total(),
                    ],
                    'Attachments retrieved successfully'
                );
            }

            return $this->successResponse($attachments, 'Attachments retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Task not found', 404, null, 'TASK_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['task_id' => $taskId]);
            return $this->errorResponse(
                'Failed to fetch attachments: ' . $e->getMessage(),
                500,
                null,
                'ATTACHMENT_FETCH_ERROR'
            );
        }
    }

    /**
     * Get attachment by ID
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $attachment = TaskAttachment::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->with(['uploader:id,name,email', 'task:id,name', 'currentVersion'])
                ->firstOrFail();

            return $this->successResponse($attachment, 'Attachment retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Attachment not found', 404, null, 'ATTACHMENT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['attachment_id' => $id]);
            return $this->errorResponse(
                'Failed to fetch attachment: ' . $e->getMessage(),
                500,
                null,
                'ATTACHMENT_FETCH_FAILED'
            );
        }
    }

    /**
     * Upload attachment for a task
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'task_id' => 'required|string|exists:tasks,id',
                'file' => 'required|file|max:51200',
                'description' => 'nullable|string|max:1000',
                'category' => 'nullable|string|in:' . implode(',', TaskAttachment::VALID_CATEGORIES),
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'is_public' => 'nullable|boolean',
                'metadata' => 'nullable|array'
            ]);

            $file = $request->file('file');
            $taskId = $validated['task_id'];
            $tenantId = $this->getTenantId();

            $task = \App\Models\Task::where('id', $taskId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            if (!Auth::user()->can('upload-attachments', $task)) {
                throw new AuthorizationException('You do not have permission to upload attachments to this task');
            }

            $attachment = TaskAttachment::createFromUpload(
                $file,
                $taskId,
                (string) Auth::id(),
                [
                    'description' => $validated['description'] ?? null,
                    'category' => $validated['category'] ?? null,
                    'tags' => $validated['tags'] ?? null,
                    'is_public' => $validated['is_public'] ?? false,
                    'metadata' => $validated['metadata'] ?? null
                ]
            );

            $attachment->load(['uploader:id,name,email', 'task:id,name']);

            Log::info('Attachment uploaded successfully', [
                'attachment_id' => $attachment->id,
                'task_id' => $taskId,
                'user_id' => Auth::id(),
                'file_name' => $attachment->original_name,
            ]);

            return $this->successResponse($attachment, 'Attachment uploaded successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors(), 'VALIDATION_FAILED');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403, null, 'FORBIDDEN');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Task not found', 404, null, 'TASK_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['data' => $request->all()]);
            return $this->errorResponse(
                'Failed to upload attachment: ' . $e->getMessage(),
                500,
                null,
                'ATTACHMENT_UPLOAD_FAILED'
            );
        }
    }

    /**
     * Delete attachment
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $attachment = TaskAttachment::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            if (!Auth::user()->can('delete', $attachment)) {
                throw new AuthorizationException('You do not have permission to delete this attachment');
            }

            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();

            return $this->successResponse(null, 'Attachment deleted successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403, null, 'FORBIDDEN');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Attachment not found', 404, null, 'ATTACHMENT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['attachment_id' => $id]);
            return $this->errorResponse(
                'Failed to delete attachment: ' . $e->getMessage(),
                500,
                null,
                'ATTACHMENT_DELETE_FAILED'
            );
        }
    }

    /**
     * Download attachment
     * 
     * @param string $id
     * @return \Illuminate\Http\Response|JsonResponse
     */
    public function download(string $id)
    {
        try {
            $tenantId = $this->getTenantId();
            $attachment = TaskAttachment::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
                return $this->errorResponse('File not found', 404, null, 'FILE_NOT_FOUND');
            }

            return Storage::disk($attachment->disk)->download(
                $attachment->path,
                $attachment->original_name
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Attachment not found', 404, null, 'ATTACHMENT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['attachment_id' => $id]);
            return $this->errorResponse(
                'Failed to download attachment: ' . $e->getMessage(),
                500,
                null,
                'ATTACHMENT_DOWNLOAD_FAILED'
            );
        }
    }
}

