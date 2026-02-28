<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Services\DeliverableTemplateVersionService;
use App\Services\ZenaAuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DeliverableTemplateController extends BaseApiController
{
    public function __construct(
        private readonly ZenaAuditLogger $auditLogger,
        private readonly DeliverableTemplateVersionService $versionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $templates = DeliverableTemplate::query()
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 15), $this->maxLimit));

        return $this->listSuccessResponse($templates, 'Deliverable templates retrieved successfully');
    }

    public function show(string $id): JsonResponse
    {
        try {
            $template = $this->templateForTenant($id)->firstOrFail();

            return $this->successResponse($template, 'Deliverable template retrieved successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Deliverable template not found');
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $tenantId = $this->tenantId();
        $userId = (string) Auth::id();

        $exists = DeliverableTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $request->string('code')->toString())
            ->exists();

        if ($exists) {
            return $this->errorResponse('Template code already exists in this tenant', 422);
        }

        $template = DeliverableTemplate::create([
            'tenant_id' => $tenantId,
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'draft'),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->auditLogger->log(
            $request,
            'zena.deliverable-template.create',
            'deliverable_template',
            (string) $template->id,
            201,
            null,
            $tenantId,
            $userId
        );

        return $this->successResponse($template, 'Deliverable template created successfully', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();
            $template = $this->templateForTenant($id)->firstOrFail();

            $template->fill($request->only(['name', 'description', 'status']));
            $template->updated_by = $userId;
            $template->save();

            $this->auditLogger->log(
                $request,
                'zena.deliverable-template.update',
                'deliverable_template',
                (string) $template->id,
                200,
                null,
                $tenantId,
                $userId
            );

            return $this->successResponse($template, 'Deliverable template updated successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Deliverable template not found');
        }
    }

    public function uploadVersion(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:html,htm|max:1024',
            'placeholders_spec' => 'nullable|array',
            'placeholders_spec_json' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();
            $template = $this->templateForTenant($id)->firstOrFail();

            $file = $request->file('file');
            if ($file === null) {
                return $this->errorResponse('HTML file is required.', 422);
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($extension, ['html', 'htm'], true)) {
                return $this->errorResponse('Only .html files are allowed.', 422);
            }

            $html = (string) $file->get();
            $mime = (string) ($file->getMimeType() ?? 'text/html');
            $size = (int) $file->getSize();
            $checksum = $this->versionService->computeChecksum($html);

            $rawSpec = $request->input('placeholders_spec');
            if (!$rawSpec && $request->filled('placeholders_spec_json')) {
                $rawSpec = $request->string('placeholders_spec_json')->toString();
            }

            try {
                $placeholdersSpec = $this->versionService->normalizePlaceholdersSpec($rawSpec, $html);
            } catch (InvalidArgumentException $exception) {
                return $this->errorResponse($exception->getMessage(), 422);
            }

            $version = DB::transaction(function () use ($template, $tenantId, $userId, $html, $mime, $size, $checksum, $placeholdersSpec): DeliverableTemplateVersion {
                $draft = DeliverableTemplateVersion::query()
                    ->where('tenant_id', $tenantId)
                    ->where('deliverable_template_id', $template->id)
                    ->where('semver', 'draft')
                    ->whereNull('published_at')
                    ->first();

                $path = sprintf(
                    'deliverable-templates/%s/%s/draft/%s.html',
                    $tenantId,
                    $template->id,
                    Str::lower((string) Str::ulid())
                );

                Storage::disk('local')->put($path, $html);

                if ($draft) {
                    if ($draft->storage_path !== '') {
                        Storage::disk('local')->delete($draft->storage_path);
                    }

                    $draft->fill([
                        'version' => 'draft',
                        'storage_path' => $path,
                        'checksum_sha256' => $checksum,
                        'mime' => $mime,
                        'size' => $size,
                        'placeholders_spec_json' => $placeholdersSpec,
                        'updated_by' => $userId,
                        'published_at' => null,
                        'published_by' => null,
                    ]);
                    $draft->save();

                    $version = $draft;
                } else {
                    $version = DeliverableTemplateVersion::create([
                        'tenant_id' => $tenantId,
                        'deliverable_template_id' => $template->id,
                        'version' => 'draft',
                        'semver' => 'draft',
                        'storage_path' => $path,
                        'checksum_sha256' => $checksum,
                        'mime' => $mime,
                        'size' => $size,
                        'placeholders_spec_json' => $placeholdersSpec,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                $template->status = 'draft';
                $template->updated_by = $userId;
                $template->save();

                return $version;
            });

            $this->auditLogger->log(
                $request,
                'zena.deliverable-template.upload-version',
                'deliverable_template',
                (string) $template->id,
                201,
                null,
                $tenantId,
                $userId,
                ['entity_id' => (string) $version->id]
            );

            return $this->successResponse($version, 'Deliverable template draft uploaded successfully', 201);
        } catch (ModelNotFoundException) {
            return $this->notFound('Deliverable template not found');
        }
    }

    public function publishVersion(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();
            $template = $this->templateForTenant($id)->firstOrFail();

            $published = DB::transaction(function () use ($template, $tenantId, $userId): DeliverableTemplateVersion {
                $draft = DeliverableTemplateVersion::query()
                    ->where('tenant_id', $tenantId)
                    ->where('deliverable_template_id', $template->id)
                    ->where('semver', 'draft')
                    ->whereNull('published_at')
                    ->first();

                if (!$draft) {
                    throw new InvalidArgumentException('No draft version available to publish.');
                }

                $nextSemver = $this->nextPublishedSemver($template->id, $tenantId);
                $published = DeliverableTemplateVersion::create([
                    'tenant_id' => $tenantId,
                    'deliverable_template_id' => $template->id,
                    'version' => $nextSemver,
                    'semver' => $nextSemver,
                    'storage_path' => $draft->storage_path,
                    'checksum_sha256' => $draft->checksum_sha256,
                    'mime' => $draft->mime,
                    'size' => (int) $draft->size,
                    'placeholders_spec_json' => $draft->placeholders_spec_json,
                    'published_at' => now(),
                    'published_by' => $userId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $template->status = 'published';
                $template->updated_by = $userId;
                $template->save();

                return $published;
            });

            $this->auditLogger->log(
                $request,
                'zena.deliverable-template.publish-version',
                'deliverable_template',
                (string) $template->id,
                201,
                null,
                $tenantId,
                $userId,
                ['entity_id' => (string) $published->id]
            );

            return $this->successResponse($published, 'Deliverable template version published successfully', 201);
        } catch (ModelNotFoundException) {
            return $this->notFound('Deliverable template not found');
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function versions(Request $request, string $id): JsonResponse
    {
        try {
            $template = $this->templateForTenant($id)->firstOrFail();

            $versions = DeliverableTemplateVersion::query()
                ->where('tenant_id', $this->tenantId())
                ->where('deliverable_template_id', $template->id)
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->integer('per_page', 20), $this->maxLimit));

            return $this->listSuccessResponse($versions, 'Deliverable template versions retrieved successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Deliverable template not found');
        }
    }

    private function templateForTenant(string $id)
    {
        return DeliverableTemplate::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($id);
    }

    private function tenantId(): string
    {
        $tenantId = request()->attributes->get('tenant_id');

        if (!$tenantId && app()->bound('current_tenant_id')) {
            $tenantId = app('current_tenant_id');
        }

        if (!$tenantId) {
            throw new \RuntimeException('Tenant context missing');
        }

        return (string) $tenantId;
    }

    private function nextPublishedSemver(string $templateId, string $tenantId): string
    {
        $lastPublished = DeliverableTemplateVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('deliverable_template_id', $templateId)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();

        if (!$lastPublished) {
            return '1.0.0';
        }

        $parts = explode('.', $lastPublished->semver);
        if (count($parts) !== 3) {
            return '1.0.0';
        }

        $major = (int) $parts[0];
        $minor = (int) $parts[1];
        $patch = (int) $parts[2] + 1;

        return sprintf('%d.%d.%d', $major, $minor, $patch);
    }
}
