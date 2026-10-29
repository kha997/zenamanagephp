<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\WorkInstanceController as ApiWorkInstanceController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\DeliverableTemplateVersion;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * GAP-015: work a WorkInstance (created by applying a WorkTemplate) from the project. Reads are tenant- and
 * project-scoped queries; every change goes through the existing /api/zena work-instance endpoints.
 */
class WorkInstancePageController extends Controller
{
    use DelegatesToApiControllers;

    /** Step statuses offered in the update form; approve / reject has its own action (work.approve). */
    public const EDITABLE_STEP_STATUSES = ['pending', 'in_progress', 'completed', 'blocked'];

    public function index(string $project): View
    {
        $projectModel = $this->projectForTenant($project);

        $instances = WorkInstance::query()
            ->where('tenant_id', $this->tenantId())
            ->where('project_id', (string) $projectModel->getKey())
            ->with(['templateVersion.template'])
            ->withCount([
                'steps',
                'steps as finished_steps_count' => static fn ($query) => $query->whereIn('status', ['completed', 'approved']),
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('work-instances.index', [
            'project' => $projectModel,
            'instances' => $instances,
        ]);
    }

    public function show(string $project, string $instance): View
    {
        $projectModel = $this->projectForTenant($project);
        $instanceModel = $this->instanceForProject($projectModel, $instance)
            ->load([
                'templateVersion.template',
                'steps' => static fn ($query) => $query->orderBy('step_order'),
                'steps.values',
                'steps.attachments',
            ]);

        $deliverableVersions = DeliverableTemplateVersion::query()
            ->with('template:id,name')
            ->where('tenant_id', $this->tenantId())
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get();

        $assignees = User::query()
            ->where('tenant_id', $this->tenantId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('work-instances.show', [
            'project' => $projectModel,
            'instance' => $instanceModel,
            'deliverableVersions' => $deliverableVersions,
            'assignees' => $assignees,
            'editableStatuses' => self::EDITABLE_STEP_STATUSES,
        ]);
    }

    public function updateStep(Request $request, string $project, string $instance, string $step, ApiWorkInstanceController $api): RedirectResponse
    {
        $instanceModel = $this->instanceForProject($this->projectForTenant($project), $instance);

        $validated = $request->validate([
            'status' => ['nullable', 'in:' . implode(',', self::EDITABLE_STEP_STATUSES)],
            // The API only checks that the user exists; the page limits the choice to the current tenant.
            'assignee_id' => ['nullable', 'string', Rule::exists('users', 'id')->where('tenant_id', $this->tenantId())],
            'deadline_at' => ['nullable', 'date'],
            'field_values' => ['nullable', 'array'],
        ]);
        if (isset($validated['field_values'])) {
            $validated['field_values'] = $this->changedFieldValues($instanceModel, $step, $validated['field_values']);
        }
        $payload = array_filter($validated, static fn ($value) => $value !== null && $value !== '' && $value !== []);

        return $this->delegate(
            fn (): JsonResponse => $api->updateStep($this->buildApiRequest($request, $payload), (string) $instanceModel->getKey(), $step),
            $project,
            $instance,
            'Đã cập nhật bước'
        );
    }

    public function approveStep(Request $request, string $project, string $instance, string $step, ApiWorkInstanceController $api): RedirectResponse
    {
        $instanceModel = $this->instanceForProject($this->projectForTenant($project), $instance);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'comment' => ['nullable', 'string'],
        ]);

        return $this->delegate(
            fn (): JsonResponse => $api->approveStep($this->buildApiRequest($request, array_filter($validated)), (string) $instanceModel->getKey(), $step),
            $project,
            $instance,
            $validated['decision'] === 'approved' ? 'Đã duyệt bước' : 'Đã từ chối bước'
        );
    }

    public function storeAttachment(Request $request, string $project, string $instance, string $step, ApiWorkInstanceController $api): RedirectResponse
    {
        $instanceModel = $this->instanceForProject($this->projectForTenant($project), $instance);
        $files = $request->hasFile('file') ? ['file' => $request->file('file')] : [];

        return $this->delegate(
            fn (): JsonResponse => $api->uploadStepAttachment($this->buildApiRequest($request, [], $files), (string) $instanceModel->getKey(), $step),
            $project,
            $instance,
            'Đã tải tệp lên'
        );
    }

    public function destroyAttachment(Request $request, string $project, string $instance, string $step, string $attachment, ApiWorkInstanceController $api): RedirectResponse
    {
        $instanceModel = $this->instanceForProject($this->projectForTenant($project), $instance);

        return $this->delegate(
            fn (): JsonResponse => $api->deleteStepAttachment($this->buildApiRequest($request), (string) $instanceModel->getKey(), $step, $attachment),
            $project,
            $instance,
            'Đã xoá tệp'
        );
    }

    public function export(Request $request, string $project, string $instance, ApiWorkInstanceController $api): Response
    {
        $instanceModel = $this->instanceForProject($this->projectForTenant($project), $instance);

        $validated = $request->validate([
            'deliverable_template_version_id' => ['required', 'string'],
            'format' => ['nullable', 'in:html,pdf'],
        ]);

        try {
            $response = $api->exportDeliverable($this->buildApiRequest($request, array_filter($validated)), (string) $instanceModel->getKey());
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xuất báo cáo.');
        }

        if ($response instanceof JsonResponse) {
            return $this->handleErrorResponse($response);
        }

        return $response;
    }

    /**
     * @param callable(): JsonResponse $call
     */
    private function delegate(callable $call, string $project, string $instance, string $successMessage): RedirectResponse
    {
        try {
            $response = $call();
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse(
            $response,
            route('app.projects.work-instances.show', ['project' => $project, 'instance' => $instance]),
            $successMessage
        );
    }

    /**
     * The form posts every editable field, but the API rewrites each submitted value; forward only fields the user
     * actually changed so untouched values (and their line endings) are kept as stored.
     *
     * @param array<array-key, mixed> $submitted
     *
     * @return array<string, mixed>
     */
    private function changedFieldValues(WorkInstance $instance, string $step, array $submitted): array
    {
        $current = WorkInstanceFieldValue::query()
            ->where('tenant_id', $this->tenantId())
            ->whereIn('work_instance_step_id', WorkInstanceStep::query()
                ->where('work_instance_id', (string) $instance->getKey())
                ->whereKey($step)
                ->select('id'))
            ->get()
            ->keyBy('field_key');

        $changed = [];
        foreach ($submitted as $fieldKey => $value) {
            $stored = $current->get((string) $fieldKey);
            $storedText = $stored === null ? '' : (string) ($stored->value_string
                ?? ($stored->value_number !== null ? (string) $stored->value_number : null)
                ?? $stored->value_date?->format('Y-m-d'));
            $submittedText = is_scalar($value) ? str_replace("\r\n", "\n", (string) $value) : null;

            if ($submittedText !== null && $submittedText === str_replace("\r\n", "\n", $storedText)) {
                continue;
            }

            $changed[(string) $fieldKey] = is_string($value) ? str_replace("\r\n", "\n", $value) : $value;
        }

        return $changed;
    }

    private function projectForTenant(string $project): Project
    {
        return Project::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($project)
            ->firstOrFail();
    }

    private function instanceForProject(Project $project, string $instance): WorkInstance
    {
        return WorkInstance::query()
            ->where('tenant_id', $this->tenantId())
            ->where('project_id', (string) $project->getKey())
            ->whereKey($instance)
            ->firstOrFail();
    }

    private function tenantId(): string
    {
        return (string) data_get(Auth::user(), 'tenant_id');
    }
}
