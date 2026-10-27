<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Project;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Services\Treasury\TreasuryDashboardService;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-097 S6 — read-only Treasury dashboard, register, timeline, reports and
 * the all-projects overview.
 */
class TreasuryReportController extends BaseApiController
{
    public function __construct(
        private readonly TreasuryDashboardService $dashboard,
        private readonly TreasuryReportService $reports,
        private readonly TreasuryTimelineService $timeline,
    ) {
    }

    public function dashboard(string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);

        return $this->successResponse($this->dashboard->dashboard($model), 'Treasury dashboard retrieved successfully');
    }

    public function register(Request $request, string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $status = (string) $request->query('status', '');
        $filters = TreasuryReportService::registerFilters($request->query()) + ['status' => in_array($status, TreasuryReportService::LEDGER_STATUSES, true) ? $status : ''];

        return $this->listSuccessResponse($this->reports->register($model, $filters, min(max(1, (int) $request->query('per_page', '50')), 100)), 'Treasury register retrieved successfully');
    }

    public function timeline(Request $request, string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $month = (string) $request->query('month', Carbon::now()->format('Y-m'));
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return $this->validationError(['month' => ['Tháng phải có dạng YYYY-MM.']]);
        }

        return $this->successResponse(['month' => $month, 'events' => $this->timeline->month($model, $month)], 'Treasury timeline retrieved successfully');
    }

    public function report(string $project, string $report): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        if (!array_key_exists($report, TreasuryReportService::REPORTS)) {
            return $this->notFound('Treasury report not found');
        }

        return $this->successResponse(['key' => $report] + $this->reports->report($model, $report), 'Treasury report retrieved successfully');
    }

    public function overview(): JsonResponse
    {
        $user = $this->user();
        $query = Project::query()->where('tenant_id', (string) $user->tenant_id)->orderBy('name');
        if (!$user->hasPermission('treasury.all_projects')) {
            $query->whereIn('id', UserRoleProject::query()->where('user_id', (string) $user->id)->whereNull('deleted_at')->pluck('project_id'));
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Project> $projects */
        $projects = $query->limit(100)->get();

        return $this->successResponse($this->dashboard->overview($projects), 'Treasury overview retrieved successfully');
    }

    private function findProject(string $id): ?Project
    {
        return Project::query()
            ->where('tenant_id', (string) data_get(Auth::user(), 'tenant_id'))
            ->whereKey($id)
            ->first();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
