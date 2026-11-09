<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMilestoneService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-117 (Gate 2 L-1, E-1, A-1) — per-project "Mốc tiến độ" page. Routes carry rbac:project.view and the tenant-scoped
 * project lookup (404 for another tenant); every write also needs ProjectMilestoneService::canEdit (403). Validation
 * errors from the service go back to the form.
 */
class ProjectMilestonePageController extends Controller
{
    public function __construct(private readonly ProjectMilestoneService $milestones)
    {
    }

    public function index(string $project): View
    {
        $model = $this->project($project);

        return view('projects.milestones', [
            'project' => $model,
            'milestones' => $this->milestones->listForProject($model),
            'canEdit' => $this->milestones->canEdit($this->user(), $model),
        ]);
    }

    public function store(Request $request, string $project): RedirectResponse
    {
        $model = $this->editable($project);
        $this->milestones->create($model, $this->user(), $request->only(['name', 'description', 'target_date', 'order']));

        return $this->done($model, 'Đã thêm mốc.');
    }

    public function update(Request $request, string $project, string $milestone): RedirectResponse
    {
        $model = $this->editable($project);
        $this->milestones->update($model, $this->user(), $milestone, $request->only(['name', 'description', 'target_date', 'order']));

        return $this->done($model, 'Đã lưu mốc.');
    }

    public function complete(Request $request, string $project, string $milestone): RedirectResponse
    {
        $model = $this->editable($project);
        $this->milestones->complete($model, $this->user(), $milestone, $request->input('completed_date'));

        return $this->done($model, 'Đã đánh dấu mốc xong.');
    }

    public function reopen(string $project, string $milestone): RedirectResponse
    {
        $model = $this->editable($project);
        $this->milestones->reopen($model, $this->user(), $milestone);

        return $this->done($model, 'Đã mở lại mốc.');
    }

    public function cancel(Request $request, string $project, string $milestone): RedirectResponse
    {
        $model = $this->editable($project);
        $this->milestones->cancel($model, $this->user(), $milestone, $request->input('reason'));

        return $this->done($model, 'Đã huỷ mốc.');
    }

    private function done(Project $project, string $message): RedirectResponse
    {
        return redirect()->route('app.projects.milestones.index', $project->id)->with('success', $message);
    }

    private function editable(string $id): Project
    {
        $project = $this->project($id);
        abort_unless($this->milestones->canEdit($this->user(), $project), 403);

        return $project;
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
