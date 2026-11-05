<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectOppm\OppmDecision;
use App\Models\User;
use App\Services\ProjectOppm\OppmDecisionService;
use App\Services\ProjectOppm\OppmOverlayService;
use App\Services\ProjectOppm\OppmRuleViolation;
use App\Services\ProjectOppm\OppmSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-110 (Gate 2 A-1, D-1, E-1, K-1, Q-1) — OPPM writes and the decision / history / compare pages. Routes carry
 * rbac:project.view and the tenant-scoped project lookup; every write also needs OppmOverlayService::canEdit (403).
 */
class ProjectOppmManageController extends Controller
{
    public function __construct(
        private readonly OppmOverlayService $overlay,
        private readonly OppmDecisionService $decisions,
        private readonly OppmSnapshotService $snapshots,
    ) {
    }

    public function saveWorkstream(Request $request, string $project, string $component): RedirectResponse
    {
        $model = $this->editable($project);
        $data = $request->validate([
            'outcome' => ['nullable', 'string', 'max:500'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'next_action' => ['nullable', 'string', 'max:500'],
            'next_action_owner_id' => ['nullable', 'string'],
            'next_action_due' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return $this->attempt(fn () => $this->overlay->saveWorkstream($model, $this->user(), $component, $data), route('app.projects.oppm', $model->id), 'Đã lưu nhóm công việc.');
    }

    public function saveSummary(Request $request, string $project): RedirectResponse
    {
        $model = $this->editable($project);
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:500'],
            'next_action_owner_id' => ['nullable', 'string'],
            'next_action_due' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return $this->attempt(fn () => $this->overlay->saveProject($model, $this->user(), $data), route('app.projects.oppm', $model->id), 'Đã lưu tóm tắt quản trị.');
    }

    public function decisions(string $project): View
    {
        $model = $this->project($project);
        $canEdit = $this->overlay->canEdit($this->user(), $model);

        return view('projects.oppm-decisions', [
            'project' => $model,
            'decisions' => $this->decisions->allForProject($model),
            'canEdit' => $canEdit,
            'users' => User::query()->where('tenant_id', (string) $model->tenant_id)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function storeDecision(Request $request, string $project): RedirectResponse
    {
        $model = $this->editable($project);
        $data = $request->validate($this->decisionRules());

        return $this->attempt(fn () => $this->decisions->create($model, $this->user(), $data), route('app.projects.oppm.decisions', $model->id), 'Đã thêm quyết định.');
    }

    public function updateDecision(Request $request, string $project, string $decision): RedirectResponse
    {
        $model = $this->editable($project);
        $row = $this->decision($model, $decision);
        $data = $request->validate($this->decisionRules());

        return $this->attempt(fn () => $this->decisions->update($model, $this->user(), $row, $data), route('app.projects.oppm.decisions', $model->id), 'Đã cập nhật quyết định.');
    }

    public function decide(Request $request, string $project, string $decision): RedirectResponse
    {
        $model = $this->editable($project);
        $row = $this->decision($model, $decision);
        $data = $request->validate(['outcome' => ['required', 'string', 'max:2000']]);

        return $this->attempt(fn () => $this->decisions->decide($model, $this->user(), $row, (string) $data['outcome']), route('app.projects.oppm.decisions', $model->id), 'Đã ghi kết quả quyết định.');
    }

    public function cancelDecision(Request $request, string $project, string $decision): RedirectResponse
    {
        $model = $this->editable($project);
        $row = $this->decision($model, $decision);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->attempt(fn () => $this->decisions->cancel($model, $this->user(), $row, (string) $data['reason']), route('app.projects.oppm.decisions', $model->id), 'Đã huỷ quyết định.');
    }

    public function closeSnapshot(Request $request, string $project): RedirectResponse
    {
        $model = $this->editable($project);
        $data = $request->validate(['period_label' => ['required', 'string', 'max:100']]);

        try {
            $snapshot = $this->snapshots->close($model, $this->user(), (string) $data['period_label']);
        } catch (OppmRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'period_label' => $e->getMessage()]);
        }

        return redirect()->route('app.projects.oppm.snapshots.show', [$model->id, $snapshot->id])->with('success', 'Đã chốt báo cáo kỳ ' . $snapshot->period_label . '.');
    }

    public function history(string $project): View
    {
        $model = $this->project($project);

        return view('projects.oppm-history', [
            'project' => $model,
            'snapshots' => $this->snapshots->history($model),
        ]);
    }

    public function compare(string $project, string $snapshot): View
    {
        $model = $this->project($project);
        $row = $this->snapshots->find($model, $snapshot);
        abort_if($row === null, 404);

        return view('projects.oppm-compare', [
            'project' => $model,
            'diff' => $this->snapshots->compare($row),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function decisionRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:2000'],
            'decision_maker_id' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private function attempt(callable $action, string $redirect, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (OppmRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'project' => $e->getMessage()]);
        }

        return redirect()->to($redirect)->with('success', $message);
    }

    private function editable(string $id): Project
    {
        $project = $this->project($id);
        abort_unless($this->overlay->canEdit($this->user(), $project), 403);

        return $project;
    }

    private function decision(Project $project, string $id): OppmDecision
    {
        $decision = $this->decisions->find($project, $id);
        abort_if($decision === null, 404);

        return $decision;
    }

    private function project(string $id): Project
    {
        /** @var Project $project */
        $project = Project::query()->where('tenant_id', (string) $this->user()->tenant_id)->findOrFail($id);

        return $project;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
