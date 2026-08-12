<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentDecision;
use App\Enums\DocumentLifecycleStatus;
use App\Exceptions\DocumentWorkflowException;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Component;
use App\Models\Document;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Task;
use App\Services\DocumentLifecycleService;
use App\Services\DocumentWorkflowService;
use App\Services\DocumentCreationService;
use App\Services\DocumentStatusService;
use App\Services\DocumentVersionService;
use App\Services\ErrorEnvelopeService;
use App\Services\ZenaAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Src\Foundation\Services\FileStorageService;
use Throwable;

class SimpleDocumentController extends Controller
{
    use ZenaContractResponseTrait;

    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE = 100;
    private const LINKABLE_MODELS = [
        Document::ENTITY_TYPE_TASK => Task::class,
        Document::ENTITY_TYPE_COMPONENT => Component::class,
        Document::ENTITY_TYPE_CR => ChangeRequest::class,
        Document::ENTITY_TYPE_SUBMITTAL => Submittal::class,
    ];
    private const PROTECTED_METADATA_KEYS = [
        'status',
        'submitted_by',
        'submitted_at',
        'decision',
        'decision_by',
        'decision_at',
        'decision_note',
        'lifecycle_status',
        'approval_status',
    ];

    public function index(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $statusService = app(DocumentStatusService::class);
        $paginator = Document::query()
            ->with('currentVersion')
            ->when($request->filled('project_id'), fn (Builder $query) => $query->where('project_id', $request->string('project_id')))
            ->when($request->filled('document_type'), fn (Builder $query) => $query->where('document_type', $request->string('document_type')))
            ->when($request->filled('discipline'), fn (Builder $query) => $query->where('discipline', $request->string('discipline')))
            ->when($request->filled('package'), fn (Builder $query) => $query->where('package', $request->string('package')))
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $statusService->applyLegacyStatusFilter($query, $request->string('status')->toString())
            )
            ->when(
                $request->filled('lifecycle_status') && DocumentLifecycleStatus::tryFrom($request->string('lifecycle_status')->toString()) !== null,
                fn (Builder $query) => $statusService->applyLifecycleFilter(
                    $query,
                    DocumentLifecycleStatus::from($request->string('lifecycle_status')->toString())
                )
            )
            ->when(
                $request->filled('approval_status') && DocumentApprovalStatus::tryFrom($request->string('approval_status')->toString()) !== null,
                fn (Builder $query) => $statusService->applyApprovalFilter(
                    $query,
                    DocumentApprovalStatus::from($request->string('approval_status')->toString())
                )
            )
            ->when($request->filled('revision'), fn (Builder $query) => $query->where('revision', $request->string('revision')))
            ->when($request->filled('linked_entity_type'), fn (Builder $query) => $query->where('linked_entity_type', strtolower($request->string('linked_entity_type')->trim()->toString())))
            ->when($request->filled('linked_entity_id'), fn (Builder $query) => $query->where('linked_entity_id', $request->string('linked_entity_id')))
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
            'document_type' => ['required', Rule::in(Document::VALID_DOCUMENT_TYPES)],
            'discipline' => 'nullable|string|max:100',
            'package' => 'nullable|string|max:100',
            'status' => ['nullable', 'string', Rule::in(['active', 'draft'])],
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
                $document = app(DocumentCreationService::class)->create([
                    'project_id' => $projectId,
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
                    'version' => $versionNumber,
                    'is_current_version' => true,
                ], $tenantId, (string) $user->id);

                app(DocumentVersionService::class)->createVersion(
                    $tenantId,
                    (string) $document->id,
                    (string) $user->id,
                    [
                        'file_path' => $fileInfo['path'],
                        'storage_driver' => config('filesystems.default', 'local'),
                        'comment' => null,
                        'metadata' => [
                            'original_filename' => $fileInfo['original_name'],
                            'mime_type' => $fileInfo['mime_type'],
                            'size' => (int) $fileInfo['size'],
                        ],
                        'expected_version_number' => $versionNumber,
                    ]
                );

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

        $this->authorize('view', $document);

        return $this->zenaSuccessResponse($document);
    }

    public function attachLink(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'linked_entity_type' => ['required', 'string', Rule::in(array_keys(self::LINKABLE_MODELS))],
            'linked_entity_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $this->authorize('update', $document);

        $data = $validator->validated();
        $linkedEntityType = $data['linked_entity_type'];
        $linkedEntityId = $data['linked_entity_id'];
        $linkedEntity = $this->findLinkableEntity($document, $linkedEntityType, $linkedEntityId);

        if (!$linkedEntity) {
            return ErrorEnvelopeService::notFoundError('Linked entity');
        }

        $document->forceFill([
            'linked_entity_type' => $linkedEntityType,
            'linked_entity_id' => $linkedEntityId,
            'updated_by' => Auth::id(),
        ])->save();

        app(ZenaAuditLogger::class)->log(
            $request,
            'zena.document.link.attach',
            'document',
            (string) $document->id,
            200,
            $document->project_id !== null ? (string) $document->project_id : null,
            (string) $document->tenant_id,
            Auth::id()
        );

        return $this->zenaSuccessResponse($document->fresh(['currentVersion']), 'Document link attached successfully');
    }

    public function detachLink(Request $request, string $id): JsonResponse
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $this->authorize('update', $document);

        $document->forceFill([
            'linked_entity_type' => null,
            'linked_entity_id' => null,
            'updated_by' => Auth::id(),
        ])->save();

        app(ZenaAuditLogger::class)->log(
            $request,
            'zena.document.link.detach',
            'document',
            (string) $document->id,
            200,
            $document->project_id !== null ? (string) $document->project_id : null,
            (string) $document->tenant_id,
            Auth::id()
        );

        return $this->zenaSuccessResponse($document->fresh(['currentVersion']), 'Document link detached successfully');
    }

    public function submit(string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(DocumentWorkflowService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('update', $documentForAuth);

        try {
            $document = app(DocumentWorkflowService::class)->submit($tenantId, $id, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be submitted from draft status'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document submitted successfully');
    }

    public function decision(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'decision' => ['required', 'string', Rule::in(array_map(fn (DocumentDecision $c) => $c->value, DocumentDecision::cases()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $decision = DocumentDecision::from($data['decision']);
        $note = $data['note'] ?? null;
        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(DocumentWorkflowService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('approve', $documentForAuth);

        try {
            $document = app(DocumentWorkflowService::class)->decide($tenantId, $id, (string) Auth::id(), $decision, $note);
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be decided from submitted status'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document decision recorded successfully');
    }

    public function publish(string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(DocumentLifecycleService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('update', $documentForAuth);

        try {
            $document = app(DocumentLifecycleService::class)->publish($tenantId, $id, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be published from draft or in-review state'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document published successfully');
    }

    public function archive(string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(DocumentLifecycleService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('update', $documentForAuth);

        try {
            $document = app(DocumentLifecycleService::class)->archive($tenantId, $id, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be archived from published state'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document archived successfully');
    }

    public function reopen(string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(DocumentLifecycleService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('update', $documentForAuth);

        try {
            $document = app(DocumentLifecycleService::class)->reopen($tenantId, $id, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be reopened after an approval decision'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document reopened successfully');
    }

    public function reactivate(string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(DocumentLifecycleService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('update', $documentForAuth);

        try {
            $document = app(DocumentLifecycleService::class)->reactivate($tenantId, $id, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be reactivated from archived state'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document reactivated successfully');
    }

    public function download(string $id)
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $this->authorize('download', $document);

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
            'document_type' => ['nullable', Rule::in(Document::VALID_DOCUMENT_TYPES)],
            'discipline' => 'nullable|string|max:100',
            'package' => 'nullable|string|max:100',
            'status' => ['nullable', 'string', Rule::in(['active', 'draft', 'review', 'in-review'])],
            'revision' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
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

        $this->authorize('update', $document);
        $tenantId = $this->resolveTenantId();

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
        $data = $validator->validated();

        $fileType = strtolower(trim((string) ($fileInfo['extension'] ?? '')));
        if ($fileType === '') {
            $fileType = 'document';
        }

        $documentAttributes = [
            'uploaded_by' => $user->id,
            'original_name' => $fileInfo['original_name'],
            'file_path' => $fileInfo['path'],
            'file_type' => $fileType,
            'mime_type' => $fileInfo['mime_type'],
            'file_size' => (int) $fileInfo['size'],
            'file_hash' => Str::ulid(),
        ];
        // Only validated fields the caller actually sent are applied; anything absent keeps
        // the value the locked row already holds inside the service's transaction.
        foreach (['document_type', 'discipline', 'package', 'revision'] as $field) {
            if (($data[$field] ?? null) !== null) {
                $documentAttributes[$field] = $data[$field];
            }
        }
        if (($data['document_type'] ?? null) !== null) {
            $documentAttributes['category'] = $data['document_type'];
        }

        $documentMetadata = $this->buildMetadata($data);
        $documentMetadata['change_notes'] = $data['change_notes'] ?? null;

        try {
            app(DocumentVersionService::class)->createVersion(
                $tenantId,
                (string) $document->id,
                (string) $user->id,
                [
                    'file_path' => $fileInfo['path'],
                    'storage_driver' => config('filesystems.default', 'local'),
                    'comment' => $data['change_notes'] ?? null,
                    'metadata' => [
                        'original_filename' => $fileInfo['original_name'],
                        'mime_type' => $fileInfo['mime_type'],
                        'size' => (int) $fileInfo['size'],
                    ],
                    'expected_version_number' => ($data['version'] ?? null) === null
                        ? null
                        : (int) $data['version'],
                    'generic_lifecycle_status' => $data['status'] ?? null,
                    'document_metadata' => $documentMetadata,
                    'document_attributes' => $documentAttributes,
                ]
            );
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                'VERSION_SEQUENCE_MISMATCH' => ErrorEnvelopeService::validationError([
                    'version' => ['Version must match the next sequential version number.'],
                ]),
                'INVALID_GENERIC_LIFECYCLE_TARGET' => ErrorEnvelopeService::validationError([
                    'status' => ['Unsupported generic lifecycle status.'],
                ]),
                default => ErrorEnvelopeService::validationError([
                    'status' => ['A new version requires a document that is not in an approval cycle.'],
                ]),
            };
        }

        return $this->zenaSuccessResponse(
            $this->findDocument($id),
            'Document version created successfully',
            201
        );
    }

    public function getVersions(string $id)
    {
        $document = $this->findDocument($id);

        if (!$document) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        $this->authorize('view', $document);

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

        $this->authorize('update', $document);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'document_type' => ['nullable', Rule::in(Document::VALID_DOCUMENT_TYPES)],
            'discipline' => 'nullable|string|max:100',
            'package' => 'nullable|string|max:100',
            'status' => ['nullable', 'string', Rule::in(['active', 'draft', 'review', 'in-review'])],
            'revision' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $updatePayload = [];

        if (isset($data['title'])) {
            $updatePayload['name'] = $data['title'];
            $updatePayload['title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $updatePayload['description'] = $data['description'];
        }

        if (isset($data['document_type'])) {
            $updatePayload['document_type'] = $data['document_type'];
            $updatePayload['category'] = $data['document_type'];
        }

        if (array_key_exists('discipline', $data)) {
            $updatePayload['discipline'] = $data['discipline'];
        }

        if (array_key_exists('package', $data)) {
            $updatePayload['package'] = $data['package'];
        }

        if (array_key_exists('revision', $data)) {
            $updatePayload['revision'] = $data['revision'];
        }

        try {
            $result = DB::transaction(function () use ($document, $data, $updatePayload) {
                /** @var Document|null $locked */
                $locked = Document::query()
                    ->where('tenant_id', $this->resolveTenantId())
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())
                        ->setModel(Document::class, [$document->id]);
                }

                $metadata = $locked->metadata ?? [];
                foreach (['document_type', 'discipline', 'package', 'revision'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $metadata[$field] = $data[$field];
                    }
                }
                if (array_key_exists('tags', $data)) {
                    $metadata['tags'] = $data['tags'];
                }
                if ($metadata !== ($locked->metadata ?? [])) {
                    $locked->forceFill(['metadata' => $metadata]);
                }
                $locked->forceFill($updatePayload);

                if (array_key_exists('status', $data)) {
                    $validationErrors = $this->writeGenericLifecycle($locked, $data['status'], (string) Auth::id());
                    if ($validationErrors !== null) {
                        return ErrorEnvelopeService::validationError($validationErrors);
                    }
                } elseif (!empty($updatePayload)) {
                    $locked->forceFill(['updated_by' => Auth::id()]);
                }

                if ($locked->isDirty()) {
                    $locked->save();
                }

                return $locked->fresh();
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ErrorEnvelopeService::notFoundError('Document');
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return $this->zenaSuccessResponse($result);
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

    private function resolveTenantId(): string
    {
        $user = Auth::user();

        return (string) (app()->bound('current_tenant_id') ? app('current_tenant_id') : ($user?->tenant_id ?? ''));
    }

    private function findDocument(string $id): ?Document
    {
        return Document::query()
            ->with('currentVersion')
            ->where('tenant_id', $this->resolveTenantId())
            ->find($id);
    }

    private function findLinkableEntity(Document $document, string $linkedEntityType, string $linkedEntityId): ?object
    {
        $modelClass = self::LINKABLE_MODELS[$linkedEntityType] ?? null;

        if ($modelClass === null) {
            return null;
        }

        $query = $modelClass::query()
            ->where('tenant_id', $document->tenant_id)
            ->whereKey($linkedEntityId);

        if ($document->project_id !== null) {
            $query->where('project_id', $document->project_id);
        }

        return $query->first();
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
            $metadata = array_merge($metadata, Arr::except($input['metadata'], self::PROTECTED_METADATA_KEYS));
        }

        foreach (['document_type', 'discipline', 'package', 'revision'] as $field) {
            if (array_key_exists($field, $input)) {
                $metadata[$field] = $input[$field];
            }
        }

        if (array_key_exists('tags', $input)) {
            $metadata['tags'] = $input['tags'] ?? [];
        }

        return $metadata;
    }

    /** @return array<string, array<int, string>>|null */
    private function writeGenericLifecycle(Document $document, string $input, string $actorId): ?array
    {
        $lifecycle = match ($input) {
            'active', 'draft' => DocumentLifecycleStatus::DRAFT,
            'review', 'in-review' => DocumentLifecycleStatus::IN_REVIEW,
            default => null,
        };
        if ($lifecycle === null) {
            return ['status' => ['Unsupported generic lifecycle status.']];
        }
        $statusService = app(DocumentStatusService::class);
        $approval = $statusService->approval($document);

        if ($approval !== DocumentApprovalStatus::NOT_SUBMITTED) {
            return ['status' => ['Generic lifecycle updates require a not-submitted document.']];
        }

        $statusService->writeState($document, $lifecycle, $approval, $actorId);

        return null;
    }

}
