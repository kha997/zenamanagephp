<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectOppm\ProjectOppmReadService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-104 (Gate 2 V-1, R-1): read-only one-page OPPM for a single project. The route carries rbac:project.view; the
 * project is loaded inside the user's tenant (404 otherwise); the Treasury block shows only with treasury.view-project.
 */
class ProjectOppmController extends Controller
{
    public function __construct(private readonly ProjectOppmReadService $oppm)
    {
    }

    public function show(string $projectId): View
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Project $project */
        $project = Project::query()
            ->with(['manager', 'client'])
            ->where('tenant_id', (string) $user->tenant_id)
            ->findOrFail($projectId);

        return view('projects.oppm', [
            'project' => $project,
            'oppm' => $this->oppm->forProject($project),
            'canViewTreasury' => Gate::allows('treasury.view-project', $project),
        ]);
    }
}
