<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\Rfi;
use App\Models\Submittal;
use App\Models\Task;
use App\Models\UserRoleProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DesignerDashboardController extends Controller
{
    use ZenaContractResponseTrait;

    public function getOverview(Request $request): JsonResponse
    {
        $user = Auth::user();

        $projectIds = $this->actorProjectIds($user, $request->input('project_id'));

        $tasks = Task::whereIn('project_id', $projectIds)
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('assigned_to', (string) $user->id)
            ->get();

        $rfis = Rfi::whereIn('project_id', $projectIds)
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('assigned_to', (string) $user->id)
            ->get();

        $submittals = Submittal::whereIn('project_id', $projectIds)
            ->where('tenant_id', (string) $user->tenant_id)
            ->get();

        return $this->zenaSuccessResponse([
            'designer_widget' => [
                'widget_key' => 'designer_summary',
                'tasks' => [
                    'total' => $tasks->count(),
                    'pending' => $tasks->where('status', Task::STATUS_PENDING)->count(),
                    'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->count(),
                    'completed' => $tasks->where('status', Task::STATUS_COMPLETED)->count(),
                ],
                'rfis' => [
                    'total' => $rfis->count(),
                    'pending' => $rfis->where('status', 'pending')->count(),
                    'answered' => $rfis->where('status', 'answered')->count(),
                    'closed' => $rfis->where('status', 'closed')->count(),
                ],
                'submittals' => [
                    'total' => $submittals->count(),
                    'draft' => $submittals->where('status', 'draft')->count(),
                    'submitted' => $submittals->where('status', 'submitted')->count(),
                    'pending_review' => $submittals->where('status', 'pending_review')->count(),
                    'approved' => $submittals->where('status', 'approved')->count(),
                    'rejected' => $submittals->where('status', 'rejected')->count(),
                ],
            ],
        ]);
    }

    public function getRfisToAnswer(Request $request): JsonResponse
    {
        $user = Auth::user();

        $projectIds = $this->actorProjectIds($user, $request->input('project_id'));

        $status = $request->input('status', 'pending');

        $rfis = Rfi::whereIn('project_id', $projectIds)
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('assigned_to', (string) $user->id)
            ->where('status', $status)
            ->with(['project:id,name', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($rfi) => [
                'id' => (string) $rfi->id,
                'title' => $rfi->title,
                'description' => $rfi->description,
                'status' => $rfi->status,
                'priority' => $rfi->priority,
                'due_date' => $rfi->due_date,
                'project' => $rfi->project ? ['id' => (string) $rfi->project->id, 'name' => $rfi->project->name] : null,
                'created_by' => $rfi->createdBy ? ['id' => (string) $rfi->createdBy->id, 'name' => $rfi->createdBy->name] : null,
                'created_at' => $rfi->created_at,
            ]);

        return $this->zenaSuccessResponse($rfis);
    }

    private function actorProjectIds($user, ?string $projectIdFilter): array
    {
        $query = UserRoleProject::where('user_id', (string) $user->id);

        if ($projectIdFilter !== null) {
            $query->where('project_id', $projectIdFilter);
        }

        return $query->pluck('project_id')->all();
    }
}
