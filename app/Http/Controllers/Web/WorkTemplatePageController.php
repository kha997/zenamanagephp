<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use App\Services\WorkTemplateCrudService;
use App\Services\WorkTemplates\WorkTemplateEditorService;
use App\Services\ZenaAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GAP-123 (Gate 2 E-1, P-1, S-1) — web pages to author work templates: list, create, edit the draft (steps + preview),
 * publish, duplicate. Writes go through WorkTemplateCrudService (draft / publish / versioning, same as the JSON API) and
 * are audited with the API's action names. Templates are scoped to the user's company.
 */
class WorkTemplatePageController extends Controller
{
    public function __construct(
        private readonly WorkTemplateCrudService $crud,
        private readonly WorkTemplateEditorService $editor,
        private readonly ZenaAuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $templates = WorkTemplate::query()
            ->where('tenant_id', $this->tenantId())
            ->with(['versions' => fn ($query) => $query->orderByDesc('created_at')])
            ->orderBy('name')
            ->get();

        return view('work-templates.index', [
            'templates' => $templates,
            'canEdit' => $this->user()->hasPermission('template.edit_draft'),
        ]);
    }

    public function create(): View
    {
        return view('work-templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('work_templates', 'code')->where('tenant_id', $this->tenantId())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'code.unique' => 'Mã mẫu đã tồn tại.',
            'code.regex' => 'Mã mẫu chỉ gồm chữ không dấu, số, dấu chấm, gạch ngang, gạch dưới.',
        ]);

        $template = $this->crud->createTemplate(
            new Request(['code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'] ?? null, 'steps' => []]),
            $this->tenantId(),
            (string) $this->user()->id,
        );
        $this->audit->log($request, 'zena.work-template.create', 'work_template', $this->attr($template, 'id'), 201, null, $this->tenantId(), (string) $this->user()->id);

        return redirect()->route('app.work-templates.edit', $this->attr($template, 'id'))->with('success', 'Đã tạo mẫu. Thêm các bước rồi lưu nháp.');
    }

    public function edit(string $id): View
    {
        $template = $this->template($id);
        $version = $this->editor->editableVersion($template);
        $editable = $this->editor->isEditable($version);
        $apiSteps = $editable ? $this->editor->apiSteps($version) : [];

        return view('work-templates.edit', [
            'template' => $template,
            'version' => $version,
            'editable' => $editable,
            'rows' => $editable ? $this->editor->rows($version) : [],
            'preview' => $this->editor->preview($apiSteps),
            'hasDraft' => $version !== null && $version->getAttribute('published_at') === null,
            'latestPublished' => $this->latestPublished($template),
            'types' => WorkTemplateEditorService::TYPES,
            'priorities' => WorkTemplateEditorService::PRIORITIES,
            'canPublish' => $this->user()->hasPermission('template.publish'),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $template = $this->template($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Form rows, including the blank rows the page adds; the step limit is checked on the kept steps (editor service).
            'steps' => ['nullable', 'array', 'max:' . (WorkTemplateEditorService::MAX_STEPS + 20)],
        ]);
        $source = $this->editor->editableVersion($template);
        abort_unless($this->editor->isEditable($source), 422, 'Mẫu này chỉ sửa được qua API.');

        $steps = $this->editor->stepsFromRows((array) ($data['steps'] ?? []), $source);
        $this->crud->updateTemplate(
            new Request(['name' => $data['name'], 'description' => $data['description'] ?? null, 'steps' => $steps]),
            $template,
            $this->tenantId(),
            (string) $this->user()->id,
        );
        $this->audit->log($request, 'zena.work-template.update', 'work_template', $this->attr($template, 'id'), 200, null, $this->tenantId(), (string) $this->user()->id);

        return redirect()->route('app.work-templates.edit', $this->attr($template, 'id'))->with('success', 'Đã lưu bản nháp.');
    }

    public function publish(Request $request, string $id): RedirectResponse
    {
        $template = $this->template($id);

        try {
            $published = $this->crud->publishTemplate($template, $this->tenantId(), (string) $this->user()->id);
        } catch (\RuntimeException) {
            return back()->withErrors(['publish' => 'Không có bản nháp để phát hành.']);
        }
        $this->audit->log($request, 'zena.work-template.publish', 'work_template', $this->attr($template, 'id'), 200, null, $this->tenantId(), (string) $this->user()->id, ['entity_id' => $this->attr($published, 'id')]);

        return redirect()->route('app.work-templates.edit', $this->attr($template, 'id'))->with('success', 'Đã phát hành phiên bản ' . $this->attr($published, 'semver') . '. PM có thể áp dụng mẫu này cho dự án.');
    }

    public function duplicate(Request $request, string $id): RedirectResponse
    {
        $template = $this->template($id);
        $source = $this->editor->editableVersion($template);
        if (!$this->editor->isEditable($source)) {
            throw ValidationException::withMessages(['duplicate' => 'Mẫu này chỉ nhân bản được qua API.']);
        }

        $copy = $this->crud->createTemplate(
            new Request([
                'code' => $this->editor->copyCode($template),
                'name' => mb_substr('Bản sao của ' . $this->attr($template, 'name'), 0, 255),
                'description' => $template->getAttribute('description'),
                'steps' => $this->editor->apiSteps($source),
            ]),
            $this->tenantId(),
            (string) $this->user()->id,
        );
        $this->audit->log($request, 'zena.work-template.create', 'work_template', $this->attr($copy, 'id'), 201, null, $this->tenantId(), (string) $this->user()->id, ['duplicated_from' => $this->attr($template, 'id')]);

        return redirect()->route('app.work-templates.edit', $this->attr($copy, 'id'))->with('success', 'Đã nhân bản thành mẫu nháp mới.');
    }

    private function template(string $id): WorkTemplate
    {
        /** @var WorkTemplate $template */
        $template = WorkTemplate::query()->where('tenant_id', $this->tenantId())->whereKey($id)->firstOrFail();

        return $template;
    }

    private function latestPublished(WorkTemplate $template): ?WorkTemplateVersion
    {
        /** @var WorkTemplateVersion|null $version */
        $version = WorkTemplateVersion::query()
            ->where('tenant_id', $this->tenantId())
            ->where('work_template_id', $this->attr($template, 'id'))
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();

        return $version;
    }

    private function attr(\Illuminate\Database\Eloquent\Model $model, string $attribute): string
    {
        $value = $model->getAttribute($attribute);

        return is_scalar($value) ? (string) $value : '';
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function tenantId(): string
    {
        return (string) $this->user()->tenant_id;
    }
}
