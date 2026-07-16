<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Services\DeliverableTemplateVersionService;
use App\Services\DocumentContext\DocumentContextRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTemplatePageController extends Controller
{
    private const VALID_CONTEXTS = ['contract', 'certificate', 'project', 'quote'];

    public function __construct(
        private readonly DocumentContextRegistry $contextRegistry,
        private readonly DeliverableTemplateVersionService $versionService,
    ) {
    }

    public function index(): View
    {
        $tenantId = $this->currentTenantId();

        $templates = DeliverableTemplate::query()
            ->where('tenant_id', $tenantId)
            ->with(['latestPublishedVersion'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('document-templates.index', [
            'templates' => $templates,
            'contextLabels' => $this->contextLabels(),
        ]);
    }

    public function create(): View
    {
        return view('document-templates.form', [
            'template' => null,
            'contexts' => self::VALID_CONTEXTS,
            'contextLabels' => $this->contextLabels(),
            'placeholders' => [],
            'sampleHtml' => '',
        ]);
    }

    public function store(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'context' => ['required', 'in:contract,certificate,project,quote'],
            'html_body' => ['required', 'string', 'max:204800'],
        ]);

        $tenantId = $this->currentTenantId();
        $userId = (string) Auth::id();

        $template = DB::transaction(function () use ($validated, $tenantId, $userId): DeliverableTemplate {
            $code = Str::upper(Str::random(4)) . '-' . time();
            $template = DeliverableTemplate::create([
                'tenant_id' => $tenantId,
                'code' => $code,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => 'draft',
                'context' => $validated['context'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->createDraftVersion($template, $validated['html_body'], $userId);

            return $template;
        });

        return redirect()
            ->route('operator.document-templates.index')
            ->with('success', 'Tạo biểu mẫu thành công');
    }

    public function edit(string $id): View
    {
        $tenantId = $this->currentTenantId();
        $template = DeliverableTemplate::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $draftVersion = DeliverableTemplateVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('deliverable_template_id', $template->id)
            ->where('semver', 'draft')
            ->first();

        $htmlBody = '';
        if ($draftVersion && $draftVersion->storage_path && Storage::disk('local')->exists($draftVersion->storage_path)) {
            $htmlBody = Storage::disk('local')->get($draftVersion->storage_path);
        }

        $provider = $this->contextRegistry->get($template->context);

        return view('document-templates.form', [
            'template' => $template,
            'contexts' => self::VALID_CONTEXTS,
            'contextLabels' => $this->contextLabels(),
            'placeholders' => $provider->keys(),
            'sampleHtml' => $htmlBody,
        ]);
    }

    public function update(\Illuminate\Http\Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'html_body' => ['required', 'string', 'max:204800'],
        ]);

        $tenantId = $this->currentTenantId();
        $userId = (string) Auth::id();
        $template = DeliverableTemplate::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        if (isset($validated['name']) || isset($validated['description'])) {
            $template->update(collect($validated)->only(['name', 'description'])->toArray());
            $template->updated_by = $userId;
            $template->save();
        }

        $this->createDraftVersion($template, $validated['html_body'], $userId);

        return redirect()
            ->route('operator.document-templates.edit', $id)
            ->with('success', 'Đã lưu bản nháp mới');
    }

    public function preview(\Illuminate\Http\Request $request, string $id): Response
    {
        $tenantId = $this->currentTenantId();
        $template = DeliverableTemplate::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $htmlBody = $request->input('html_body', '');
        if ($htmlBody === '') {
            $draftVersion = DeliverableTemplateVersion::query()
                ->where('tenant_id', $tenantId)
                ->where('deliverable_template_id', $template->id)
                ->where('semver', 'draft')
                ->first();

            if ($draftVersion && $draftVersion->storage_path && Storage::disk('local')->exists($draftVersion->storage_path)) {
                $htmlBody = Storage::disk('local')->get($draftVersion->storage_path);
            }
        }

        $provider = $this->contextRegistry->get($template->context);
        $context = $provider->sample();
        $rendered = $this->versionService->renderHtml($htmlBody, $context);

        return response($rendered, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function publish(string $id): \Illuminate\Http\RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $userId = (string) Auth::id();
        $template = DeliverableTemplate::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $draft = DeliverableTemplateVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('deliverable_template_id', $template->id)
            ->where('semver', 'draft')
            ->whereNull('published_at')
            ->first();

        if (!$draft) {
            return back()->with('error', 'Không có bản nháp để xuất bản.');
        }

        DB::transaction(function () use ($template, $draft, $tenantId, $userId): void {
            $lastPublished = DeliverableTemplateVersion::query()
                ->where('tenant_id', $tenantId)
                ->where('deliverable_template_id', $template->id)
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->first();

            $nextSemver = '1.0.0';
            if ($lastPublished) {
                $parts = explode('.', $lastPublished->semver);
                if (count($parts) === 3) {
                    $nextSemver = sprintf('%d.%d.%d', (int) $parts[0], (int) $parts[1], (int) $parts[2] + 1);
                }
            }

            DeliverableTemplateVersion::create([
                'tenant_id' => $tenantId,
                'deliverable_template_id' => $template->id,
                'version' => $nextSemver,
                'semver' => $nextSemver,
                'storage_path' => $draft->storage_path,
                'checksum_sha256' => $draft->checksum_sha256,
                'mime' => $draft->mime,
                'size' => $draft->size,
                'placeholders_spec_json' => $draft->placeholders_spec_json,
                'published_at' => now(),
                'published_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $template->status = 'published';
            $template->updated_by = $userId;
            $template->save();
        });

        return back()->with('success', 'Đã xuất bản biểu mẫu');
    }

    private function createDraftVersion(DeliverableTemplate $template, string $htmlBody, string $userId): DeliverableTemplateVersion
    {
        $tenantId = (string) $template->tenant_id;

        $existingDraft = DeliverableTemplateVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('deliverable_template_id', $template->id)
            ->where('semver', 'draft')
            ->first();

        $path = sprintf(
            'deliverable-templates/%s/%s/draft/%s.html',
            $tenantId,
            $template->id,
            Str::lower((string) Str::ulid())
        );

        Storage::disk('local')->put($path, $htmlBody);

        $checksum = $this->versionService->computeChecksum($htmlBody);
        $placeholdersSpec = $this->versionService->normalizePlaceholdersSpec(null, $htmlBody);

        if ($existingDraft) {
            if ($existingDraft->storage_path !== '') {
                Storage::disk('local')->delete($existingDraft->storage_path);
            }
            $existingDraft->fill([
                'storage_path' => $path,
                'checksum_sha256' => $checksum,
                'size' => strlen($htmlBody),
                'placeholders_spec_json' => $placeholdersSpec,
                'updated_by' => $userId,
                'published_at' => null,
                'published_by' => null,
            ]);
            $existingDraft->save();

            return $existingDraft;
        }

        return DeliverableTemplateVersion::create([
            'tenant_id' => $tenantId,
            'deliverable_template_id' => $template->id,
            'version' => 'draft',
            'semver' => 'draft',
            'storage_path' => $path,
            'checksum_sha256' => $checksum,
            'mime' => 'text/html',
            'size' => strlen($htmlBody),
            'placeholders_spec_json' => $placeholdersSpec,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function currentTenantId(): string
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return (string) $user->tenant_id;
    }

    /**
     * @return array<string, string>
     */
    private function contextLabels(): array
    {
        $labels = [];
        foreach ($this->contextRegistry->all() as $slug => $provider) {
            $labels[$slug] = $provider->label();
        }
        return $labels;
    }
}
