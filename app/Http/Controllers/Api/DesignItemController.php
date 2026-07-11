<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EventRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * DesignItem — công việc thiết kế qua vòng duyệt nội bộ và phản hồi khách hàng.
 * Spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 1).
 */
class DesignItemController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'project_id',
        'work_instance_step_id',
        'name',
        'item_type',
        'review_status',
        'assigned_to',
        'due_to_client_at',
        'client_feedback_notes',
        'approval_evidence',
        'created_by',
        'created_at',
        'updated_at',
    ];

    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? Auth::user()?->tenant_id;

        return $tenantId ? (string) $tenantId : '';
    }

    private function scopedQuery(string $tenantId): Builder
    {
        return DesignItem::query()->forTenant($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DesignItem $item): array
    {
        return Arr::only($item->attributesToArray(), self::RESPONSE_FIELDS);
    }

    private function rules(string $tenantId): array
    {
        return [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'work_instance_step_id' => [
                'nullable',
                'string',
                Rule::exists('work_instance_steps', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', Rule::in(DesignItem::VALID_TYPES)],
            'assigned_to' => [
                'nullable',
                'string',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'due_to_client_at' => ['nullable', 'date'],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', DesignItem::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->input('project_id'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', (string) $request->input('review_status'));
        }

        $items = $query
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DesignItem $item): array => $this->serialize($item))
            ->values();

        return $this->zenaSuccessResponse($items, 'Design items retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', DesignItem::class);

        $validator = Validator::make($request->all(), $this->rules($tenantId));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $item = DesignItem::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $request->input('project_id'),
            'work_instance_step_id' => $request->input('work_instance_step_id'),
            'name' => (string) $request->input('name'),
            'item_type' => (string) $request->input('item_type', DesignItem::TYPE_OTHER),
            'review_status' => DesignItem::STATUS_DRAFT,
            'assigned_to' => $request->input('assigned_to'),
            'due_to_client_at' => $request->input('due_to_client_at'),
            'created_by' => (string) $user->id,
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($item->fresh() ?? $item),
            'Design item created successfully',
            201
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('view', $item);

        return $this->zenaSuccessResponse($this->serialize($item), 'Design item retrieved successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('update', $item);

        $rules = $this->rules($tenantId);
        $rules['project_id'] = ['sometimes'] + $rules['project_id'];
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // review_status is deliberately excluded here — it is only ever changed via updateStatus(),
        // which enforces the transition graph and its side-effect rules. Silently ignore it if sent.
        $item->fill($request->only([
            'project_id', 'work_instance_step_id', 'name', 'item_type', 'assigned_to', 'due_to_client_at',
        ]));
        $item->save();

        return $this->zenaSuccessResponse($this->serialize($item->fresh() ?? $item), 'Design item updated successfully');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('update', $item);

        $validator = Validator::make($request->all(), [
            'review_status' => ['required', Rule::in(DesignItem::VALID_STATUSES)],
            'client_feedback_notes' => ['nullable', 'string', 'max:2000'],
            'approval_evidence' => ['nullable', Rule::in(DesignItem::VALID_APPROVAL_EVIDENCE)],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $from = (string) $item->review_status;
        $to = (string) $request->input('review_status');

        if (!DesignItem::canTransition($from, $to)) {
            return $this->validationError([
                'review_status' => ["Cannot transition from {$from} to {$to}."],
            ]);
        }

        if ($to === DesignItem::STATUS_REVISION_REQUESTED && !$request->filled('client_feedback_notes')) {
            return $this->validationError([
                'client_feedback_notes' => ['Required when requesting a revision.'],
            ]);
        }

        if ($to === DesignItem::STATUS_SENT_TO_CLIENT) {
            if (!$item->due_to_client_at) {
                return $this->validationError([
                    'due_to_client_at' => ['Must be set before sending to client.'],
                ]);
            }

            $hasAttachment = Document::query()
                ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
                ->exists();

            if (!$hasAttachment) {
                return $this->validationError([
                    'review_status' => ['At least one attached document is required before sending to client.'],
                ]);
            }
        }

        if ($to === DesignItem::STATUS_APPROVED && !$request->filled('approval_evidence')) {
            return $this->validationError([
                'approval_evidence' => ['Required when approving — record how the client confirmed (phone/email/zalo/client_portal).'],
            ]);
        }

        $item->review_status = $to;

        if ($to === DesignItem::STATUS_REVISION_REQUESTED) {
            $item->client_feedback_notes = (string) $request->input('client_feedback_notes');
        }

        if ($to === DesignItem::STATUS_APPROVED) {
            $item->approval_evidence = (string) $request->input('approval_evidence');
        }

        $item->save();

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $item->project_id,
            'aggregate_type' => 'design_item',
            'aggregate_id' => (string) $item->id,
            'event_key' => 'design_item.status_changed',
            'actor_user_id' => (string) Auth::id(),
            'payload' => ['from' => $from, 'to' => $to],
            'occurred_at' => now(),
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($item->fresh() ?? $item),
            'Design item status updated successfully'
        );
    }

    public function uploadDocument(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('update', $item);

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $directory = sprintf('design-items/%s', $item->id);
        $storedFilename = (string) Str::ulid() . '.' . $file->getClientOriginalExtension();
        $storedPath = Storage::disk('local')->putFileAs($directory, $file, $storedFilename);

        if ($storedPath === false) {
            return $this->serverError('Failed to store file');
        }

        $document = Document::query()
            ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
            ->first();

        if (!$document instanceof Document) {
            $document = Document::query()->create([
                'tenant_id' => $tenantId,
                'project_id' => (string) $item->project_id,
                'uploaded_by' => (string) $user->id,
                'created_by' => (string) $user->id,
                'name' => (string) $file->getClientOriginalName(),
                'original_name' => (string) $file->getClientOriginalName(),
                'title' => (string) $item->name,
                'file_path' => $storedPath,
                'file_type' => (string) $file->getClientOriginalExtension(),
                'mime_type' => (string) $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'file_hash' => (string) (hash_file('sha256', $file->getRealPath()) ?: Str::random(32)),
                'linked_entity_type' => Document::ENTITY_TYPE_DESIGN_ITEM,
                'linked_entity_id' => (string) $item->id,
                'status' => 'active',
                'visibility' => Document::VISIBILITY_INTERNAL,
            ]);
        }

        $version = $document->createNewVersion([
            'file_path' => $storedPath,
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => $request->input('comment'),
            'metadata' => [
                'original_filename' => (string) $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ],
            'created_by' => (string) $user->id,
        ]);

        return $this->zenaSuccessResponse([
            'document_id' => (string) $document->id,
            'version_id' => (string) $version->id,
            'version_number' => $version->version_number,
        ], 'File uploaded successfully', 201);
    }

    public function listDocuments(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('view', $item);

        $document = Document::query()
            ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
            ->first();

        $versions = $document
            ? $document->versions()->get(['id', 'document_id', 'version_number', 'comment', 'created_by', 'created_at'])
            : collect();

        return $this->zenaSuccessResponse($versions, 'Document versions retrieved successfully');
    }
}
