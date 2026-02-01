<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class SimpleDocumentController extends Controller
{
    use ZenaContractResponseTrait;

    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE = 100;

    public function index(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $documents = Document::forCurrentTenant()->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->zenaSuccessResponse($documents);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'file_path' => 'required|string|max:255',
            'mime_type' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1',
            'file_hash' => 'nullable|string|max:255',
            'project_id' => 'nullable|string|exists:projects,id',
            'original_name' => 'nullable|string|max:255',
            'file_type' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
            'version' => 'nullable|integer|min:1',
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
            $document = Document::create([
                'tenant_id' => $tenantId,
                'project_id' => $projectId,
                'uploaded_by' => $user->id,
                'name' => $data['name'],
                'original_name' => $data['original_name'] ?? $data['name'],
                'file_path' => $data['file_path'],
                'file_type' => $data['file_type'] ?? 'document',
                'mime_type' => $data['mime_type'],
                'file_size' => $data['file_size'],
                'file_hash' => $data['file_hash'] ?? Str::ulid(),
                'category' => $data['category'] ?? 'general',
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'status' => 'active',
                'version' => $data['version'] ?? 1,
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

    public function update(Request $request, $id)
    {
        return $this->show($request, $id);
    }

    public function destroy(Request $request, $id)
    {
        return $this->show($request, $id);
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
