<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\Payroll\LabourCostReportService;
use App\Services\Payroll\ProjectPnlService;
use App\Services\ProjectOppm\OppmOverlayService;
use App\Services\ProjectOppm\OppmSnapshotService;
use App\Services\ProjectOppm\ProjectOppmReadService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-104 (Gate 2 V-1, R-1): one-page OPPM for a single project. The route carries rbac:project.view; the project is
 * loaded inside the user's tenant (404 otherwise); the Treasury block shows only with treasury.view-project.
 * GAP-110 (A-1, E-1, K-1): editors (OppmOverlayService::canEdit) see the overlay / decision / close forms; a closed
 * snapshot renders through the same body from its stored payload, read-only. GAP-113 V-1: the live page adds the
 * closed-month labour cost total for payroll.manage holders and the project's PM; snapshots do not carry it. GAP-114
 * V-1: the same audience sees the closed-month P&L summary.
 */
class ProjectOppmController extends Controller
{
    public function __construct(
        private readonly ProjectOppmReadService $oppm,
        private readonly OppmOverlayService $overlay,
        private readonly OppmSnapshotService $snapshots,
        private readonly LabourCostReportService $labour,
        private readonly ProjectPnlService $pnl,
    ) {
    }

    public function show(string $projectId): View
    {
        $user = $this->user();
        $project = $this->project($projectId);
        $canEdit = $this->overlay->canEdit($user, $project);
        $canSeeMoney = $user->hasPermission('payroll.manage') || (string) $project->getAttribute('pm_id') === (string) $user->id;

        return view('projects.oppm', [
            'project' => $project,
            'header' => $this->oppm->header($project),
            'oppm' => $this->oppm->forProject($project),
            'snapshot' => null,
            'canViewTreasury' => Gate::allows('treasury.view-project', $project),
            'canEdit' => $canEdit,
            'users' => $canEdit ? User::query()->where('tenant_id', (string) $project->tenant_id)->orderBy('name')->pluck('name', 'id')->all() : [],
            // GAP-113 V-1: closed-month labour cost total for payroll.manage and the project's PM only.
            'labourCost' => $canSeeMoney ? $this->labour->projectTotal($project) : null,
            // GAP-114 V-1: closed-month P&L summary for the same audience.
            'pnl' => $canSeeMoney ? $this->pnl->projectSummary($project) : null,
        ]);
    }

    public function snapshot(string $projectId, string $snapshotId): View
    {
        $project = $this->project($projectId);
        $snapshot = $this->snapshots->find($project, $snapshotId);
        abort_if($snapshot === null, 404);
        $payload = $snapshot->payload;

        return view('projects.oppm', [
            'project' => $project,
            'header' => (array) $payload['header'],
            'oppm' => (array) $payload['oppm'],
            'snapshot' => $snapshot,
            'canViewTreasury' => Gate::allows('treasury.view-project', $project),
            'canEdit' => false,
            'users' => [],
            'labourCost' => null,
            'pnl' => null,
        ]);
    }

    private function project(string $projectId): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->with(['manager', 'client'])
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->findOrFail($projectId);

        return $project;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
