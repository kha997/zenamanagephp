<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Services\ErrorEnvelopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Src\Foundation\Services\FileStorageService;
use Throwable;

class SimpleDocumentController extends Controller
{
    use ZenaContractResponseTrait;

    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE = 100;

    public function index(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $paginator = Document::query()
            ->with('currentVersion')
            ->when($request->filled('project_id'), fn (Builder $query) => $query->where('project_id', $request->string('project_id')))
            ->when($request->filled('document_type'), fn (Builder $query) => $query->where('document_type', $request->string('document_type')))
            ->when($request->filled('discipline'), fn (Builder $query) => $query->where('discipline', $request->string('discipline')))
            ->when($request->filled('package'), fn (Builder $query) => $query->where('package', $request->string('package')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('revision'), fn (Builder $query) => $query->where('revision', $request->string('revision')))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $search = '%' . $request->string('q')->trim() . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('title', 'like', $search)
                        ->orWhere('name', 'like', $search)
                        ->orWhere('original_name', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('document_type', 'like', $search)
                        ->orWhere('discipline', 'like', $search)
                        ->orWhere('package', 'like', $search)
                        ->orWhere('revision', 'like', $search);
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->zenaSuccessResponse($paginator);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'project_id' => 'required|string|exists:projects,id',
            'document_type' => 'required|string|max:100',
            'discipline' => 'nullable|string|max:100',
            'package' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:100',
            'revision' => 'nullable|string|max:50',
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif,zip,rar,7z',
            'name' => 'sometimes|string|max:255',
            'file_hash' => 'nullable|string|max:255',
            'file_type' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'metadata' => 'nullable|array',
            'version' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $user = Auth::user();
        if (!$user) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = (string) (app('current_tenant_id') ?? $user->tenant_id);
        if ($tenantId === '') {
            return ErrorEnvelopeService::error('TENANT_REQUIRED', 'Tenant context missing', [], 400);
        }

        $data = $validator->validated();
        $projectId = $data['project_id'] ?? null;

        if ($projectId !== null && $projectId !== '') {
            $projectExists = Project::where('tenant_id', $tenantId)
                ->where('id', $projectId)
                ->exists();

            if (!$projectExists) {
                return ErrorEnvelopeService::notFoundError('Project');
            }
        } else {
            $projectId = null;
        }

        try {
            $file = $request->file('file');
            if (!$file) {
                return ErrorEnvelopeService::error('File upload failed', 400);
            }

            $storageDirectory = 'documents/' . ($projectId ?: 'general');
            $fileStorageService = app(FileStorageService::class);
            $uploadResult = $fileStorageService->uploadFile(
                $file,
                'local',
                $storageDirectory
            );

            if (!$uploadResult['success']) {
                return ErrorEnvelopeService::error('File upload failed: ' . $uploadResult['error'], 400);
            }

            $fileInfo = $uploadResult['file'];
            $versionNumber = (int) ($data['version'] ?? 1);
            $metadata = $this->buildMetadata($data);

            $documentName = $data['name'] ??
                $data['title'] ??
                pathinfo($fileInfo['original_name'] ?? $fileInfo['filename'], PATHINFO_FILENAME) ??
                'document';

            $fileType = $data['file_type'] ?? strtolower(trim((string) ($fileInfo['extension'] ?? '')));
            if ($fileType === '') {
                $fileType = 'document';
            }

            $document = DB::transaction(function () use ($data, $documentName, $fileInfo, $fileType, $metadata, $projectId, $tenantId, $user, $versionNumber) {
                $document = Document::create([
                    'tenant_id' => $tenantId,
                    'project_id' => $projectId,
                    'uploaded_by' => $user->id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'name' => $documentName,
                    'title' => $data['title'],
                    'document_type' => $data['document_type'],
                    'discipline' => $data['discipline'] ?? null,
                    'package' => $data['package'] ?? null,
                    'revision' => $data['revision'] ?? null,
                    'original_name' => $fileInfo['original_name'],
                    'file_path' => $fileInfo['path'],
                    'file_type' => $fileType,
                    'mime_type' => $fileInfo['mime_type'],
                    'file_size' => (int) $fileInfo['size'],
                    'file_hash' => $data['file_hash'] ?? Str::ulid(),
                    'category' => $data['document_type'] ?? $data['category'] ?? 'general',
                    'description' => $data['description'] ?? null,
                    'metadata' => $metadata,
                    'status' => $data['status'] ?? 'active',
                    'version' => $versionNumber,
                    'is_current_version' => true,
                ]);

                $version = $this->createVersionRecord(
                    $document,
                    $versionNumber,
                    $user->id,
                    $fileInfo['path'],
                    $fileInfo['original_name'],
                    $fileInfo['mime_type'],
                    (int) $fileInfo['size'],
                    $metadata,
                    null
                );

                $document->forceFill(['current_version_id' => $version->id])->save();

                return $document->fresh(['currentVersion']);
            });

            return $this->zenaSuccessResponse($document, 'Document uploaded successfully', 201);
        } catch (Throwable $e) {
            Log::error('Document creation failed', ['message' => $e->getMessage()]);
            return ErrorEnvelopeService::serverError('Failed to create document');
        }
    }

    public function show(Request $request, $id)
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        return $this->zenaSuccessResponse($document);
    }

    public function download(string $id)
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $disk = config('filesystems.default', 'local');
        $path = $document->file_path;

        if (!$path || !Storage::disk($disk)->exists($path)) {
            return ErrorEnvelopeService::notFoundError('Document file not found');
        }

        return Storage::disk($disk)->download($path, $document->original_name ?? $document->name);
    }

    public function createVersion(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif,zip,rar,7z',
            'version' => 'nullable|integer|min:1',
            'change_notes' => 'nullable|string|max:1000',
            'document_type' => 'nullable|string|max:100',
            'discipline' => 'nullable|string|max:100',
            'package' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:100',
            'revision' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $user = Auth::user();
        if (!$user) {
            return ErrorEnvelopeService::authenticationError();
        }

        $document = $this->findDocument($id);
        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $file = $request->file('file');
        if (!$file) {
            return ErrorEnvelopeService::error('File upload failed', 400);
        }

        $storageDirectory = 'documents/' . ($document->project_id ?: 'general');
        $fileStorageService = app(FileStorageService::class);
        $uploadResult = $fileStorageService->uploadFile(
            $file,
            'local',
            $storageDirectory
        );

        if (!$uploadResult['success']) {
            return ErrorEnvelopeService::error('File upload failed: ' . $uploadResult['error'], 400);
        }

        $fileInfo = $uploadResult['file'];
        $requestedVersion = $request->input('version');
        $nextVersion = $this->nextVersionNumber($document);

        if ($requestedVersion !== null && (int) $requestedVersion !== $nextVersion) {
            return ErrorEnvelopeService::validationError([
                'version' => ['Version must match the next sequential version number.'],
            ]);
        }

        $versionNumber = $nextVersion;
        $metadata = $this->buildMetadata($request->all(), $document->metadata ?? []);
        $metadata['change_notes'] = $request->input('change_notes');

        $fileType = strtolower(trim((string) ($fileInfo['extension'] ?? '')));
        if ($fileType === '') {
            $fileType = 'document';
        }

        $document = DB::transaction(function () use ($document, $fileInfo, $fileType, $metadata, $request, $user, $versionNumber) {
            $version = $this->createVersionRecord(
                $document,
                $versionNumber,
                $user->id,
                $fileInfo['path'],
                $fileInfo['original_name'],
                $fileInfo['mime_type'],
                (int) $fileInfo['size'],
                $metadata,
                $request->input('change_notes')
            );

            $document->forceFill([
                'uploaded_by' => $user->id,
                'updated_by' => $user->id,
                'original_name' => $fileInfo['original_name'],
                'file_path' => $fileInfo['path'],
                'file_type' => $fileType,
                'mime_type' => $fileInfo['mime_type'],
                'file_size' => (int) $fileInfo['size'],
                'file_hash' => Str::ulid(),
                'document_type' => $request->input('document_type', $document->document_type),
                'discipline' => $request->input('discipline', $document->discipline),
                'package' => $request->input('package', $document->package),
                'revision' => $request->input('revision', $document->revision),
                'status' => $request->input('status', $document->status),
                'category' => $request->input('document_type', $document->document_type) ?: $document->category,
                'description' => $document->description,
                'metadata' => $metadata,
                'version' => $versionNumber,
                'current_version_id' => $version->id,
            ])->save();

            return $document->fresh(['currentVersion']);
        });

        return $this->zenaSuccessResponse($document, 'Document version created successfully', 201);
    }

    public function getVersions(string $id)
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $versions = $document->versions()
            ->orderByVersion()
            ->get();

        return $this->zenaSuccessResponse($versions);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'document_type' => 'nullable|string|max:100',
            'discipline' => 'nullable|string|max:100',
            'package' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:100',
            'revision' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $updatePayload = [];
        $metadata = $document->metadata ?? [];

        if (isset($data['title'])) {
            $updatePayload['name'] = $data['title'];
            $updatePayload['title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $updatePayload['description'] = $data['description'];
        }

        if (isset($data['document_type'])) {
            $updatePayload['document_type'] = $data['document_type'];
            $metadata['document_type'] = $data['document_type'];
            $updatePayload['category'] = $data['document_type'];
        }

        if (array_key_exists('discipline', $data)) {
            $updatePayload['discipline'] = $data['discipline'];
            $metadata['discipline'] = $data['discipline'];
        }

        if (array_key_exists('package', $data)) {
            $updatePayload['package'] = $data['package'];
            $metadata['package'] = $data['package'];
        }

        if (array_key_exists('status', $data)) {
            $updatePayload['status'] = $data['status'];
            $metadata['status'] = $data['status'];
        }

        if (array_key_exists('revision', $data)) {
            $updatePayload['revision'] = $data['revision'];
            $metadata['revision'] = $data['revision'];
        }

        if (isset($data['tags'])) {
            $metadata['tags'] = $data['tags'];
        }

        if ($metadata !== ($document->metadata ?? [])) {
            $updatePayload['metadata'] = $metadata;
        }

        if (!empty($updatePayload)) {
            $updatePayload['updated_by'] = Auth::id();
            $document->update($updatePayload);
        }

        return $this->zenaSuccessResponse($document->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $disk = config('filesystems.default', 'local');
        $paths = $document->versions()->pluck('file_path')
            ->push($document->file_path)
            ->filter()
            ->unique()
            ->values();

        foreach ($paths as $path) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        $document->delete();

        return $this->zenaSuccessResponse(null, 'Document deleted successfully');
    }

    private function findDocument(string $id): ?Document
    {
        return Document::query()
            ->with('currentVersion')
            ->find($id);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }

    private function buildMetadata(array $input, array $base = []): array
    {
        $metadata = $base;

        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $metadata = array_merge($metadata, $input['metadata']);
        }

        foreach (['document_type', 'discipline', 'package', 'status', 'revision'] as $field) {
            if (array_key_exists($field, $input)) {
                $metadata[$field] = $input[$field];
            }
        }

        if (array_key_exists('tags', $input)) {
            $metadata['tags'] = $input['tags'] ?? [];
        }

        return $metadata;
    }

    private function nextVersionNumber(Document $document): int
    {
        $latestVersion = (int) $document->versions()->max('version_number');
        $currentVersion = (int) $document->version;

        return max($latestVersion, $currentVersion) + 1;
    }

    private function createVersionRecord(
        Document $document,
        int $versionNumber,
        string $userId,
        string $filePath,
        string $originalName,
        string $mimeType,
        int $fileSize,
        array $metadata,
        ?string $comment
    ): DocumentVersion {
        return DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $versionNumber,
            'file_path' => $filePath,
            'storage_driver' => config('filesystems.default', 'local'),
            'comment' => $comment,
            'metadata' => array_merge($metadata, [
                'original_filename' => $originalName,
                'mime_type' => $mimeType,
                'size' => $fileSize,
            ]),
            'created_by' => $userId,
        ]);
    }
}
