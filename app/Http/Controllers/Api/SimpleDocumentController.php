<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $page = max(1, (int) $request->input('page', 1));
        $documents = Document::forCurrentTenant()->orderBy('created_at', 'desc')->get();

        if ($request->filled('document_type')) {
            $documentType = $request->input('document_type');
            $documents = $documents->filter(fn ($document) => $document->document_type === $documentType)->values();
        }

        $total = $documents->count();
        $items = $documents->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return $this->zenaSuccessResponse($paginator);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'project_id' => 'required|string|exists:projects,id',
            'document_type' => 'required|in:drawing,specification,contract,report,photo,other',
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif,zip,rar,7z',
            'name' => 'sometimes|string|max:255',
            'file_hash' => 'nullable|string|max:255',
            'file_type' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
            'version' => 'sometimes|integer|min:1',
            'parent_document_id' => 'nullable|string',
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
            $metadata = $data['metadata'] ?? [];
            $metadata['document_type'] = $data['document_type'];

            $documentName = $data['name'] ??
                $data['title'] ??
                pathinfo($fileInfo['original_name'] ?? $fileInfo['filename'], PATHINFO_FILENAME) ??
                'document';

            $fileType = $data['file_type'] ?? strtolower(trim((string) ($fileInfo['extension'] ?? '')));
            if ($fileType === '') {
                $fileType = 'document';
            }

            $version = (int) ($data['version'] ?? 1);

            $document = Document::create([
                'tenant_id' => $tenantId,
                'project_id' => $projectId,
                'uploaded_by' => $user->id,
                'name' => $documentName,
                'original_name' => $fileInfo['original_name'],
                'file_path' => $fileInfo['path'],
                'file_type' => $fileType,
                'mime_type' => $fileInfo['mime_type'],
                'file_size' => (int) $fileInfo['size'],
                'file_hash' => $data['file_hash'] ?? Str::ulid(),
                'category' => $data['document_type'] ?? $data['category'] ?? 'general',
                'description' => $data['description'] ?? null,
                'metadata' => $metadata,
                'status' => 'active',
                'version' => $version,
                'is_current_version' => true,
                'parent_document_id' => $data['parent_document_id'] ?? null,
            ]);

            return $this->zenaSuccessResponse($document, 'Document uploaded successfully', 201);
        } catch (Throwable $e) {
            Log::error('Document creation failed', ['message' => $e->getMessage()]);
            return ErrorEnvelopeService::serverError('Failed to create document');
        }
    }

    public function show(Request $request, $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        return $this->zenaSuccessResponse($document);
    }

    public function download(string $id)
    {
        $document = Document::find($id);

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
            'version' => 'required|integer|min:1',
            'change_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $user = Auth::user();
        if (!$user) {
            return ErrorEnvelopeService::authenticationError();
        }

        $document = Document::find($id);
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
        $version = (int) $request->input('version');

        $metadata = $document->metadata ?? [];
        $metadata['document_type'] = $document->document_type;
        $metadata['change_notes'] = $request->input('change_notes');

        $fileType = strtolower(trim((string) ($fileInfo['extension'] ?? '')));
        if ($fileType === '') {
            $fileType = 'document';
        }

        $newVersion = Document::create([
            'tenant_id' => $document->tenant_id,
            'project_id' => $document->project_id,
            'uploaded_by' => $user->id,
            'name' => $document->name,
            'original_name' => $fileInfo['original_name'],
            'file_path' => $fileInfo['path'],
            'file_type' => $fileType,
            'mime_type' => $fileInfo['mime_type'],
            'file_size' => (int) $fileInfo['size'],
            'file_hash' => Str::ulid(),
            'category' => $document->category ?: ($document->document_type ?: 'general'),
            'description' => $request->input('change_notes') ?? $document->description,
            'metadata' => $metadata,
            'status' => $document->status,
            'version' => $version,
            'is_current_version' => true,
            'parent_document_id' => $document->id,
        ]);

        return $this->zenaSuccessResponse($newVersion, 'Document version created successfully', 201);
    }

    public function getVersions(string $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $versions = Document::where(function ($query) use ($document) {
                $query->where('id', $document->id)
                      ->orWhere('parent_document_id', $document->id);
            })
            ->orderBy('version', 'desc')
            ->get();

        return $this->zenaSuccessResponse($versions);
    }

    public function update(Request $request, $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'document_type' => 'nullable|in:drawing,specification,contract,report,photo,other',
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $updatePayload = [];
        $metadata = $document->metadata ?? [];

        if (isset($data['title'])) {
            $updatePayload['name'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $updatePayload['description'] = $data['description'];
        }

        if (isset($data['document_type'])) {
            $metadata['document_type'] = $data['document_type'];
            $updatePayload['category'] = $data['document_type'];
        }

        if (isset($data['tags'])) {
            $metadata['tags'] = $data['tags'];
        }

        if ($metadata !== ($document->metadata ?? [])) {
            $updatePayload['metadata'] = $metadata;
        }

        if (!empty($updatePayload)) {
            $document->update($updatePayload);
        }

        return $this->zenaSuccessResponse($document->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $disk = config('filesystems.default', 'local');
        if ($document->file_path && Storage::disk($disk)->exists($document->file_path)) {
            Storage::disk($disk)->delete($document->file_path);
        }

        $document->delete();

        return $this->zenaSuccessResponse(null, 'Document deleted successfully');
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
