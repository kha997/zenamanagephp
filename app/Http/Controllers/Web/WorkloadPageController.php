<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DesignItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
/**
 * Trang "Khối lượng công việc" — việc đang mở (Task + Hạng mục thiết kế)
 * nhóm theo người, sắp theo tải giảm dần.
 * Spec: docs/superpowers/specs/2026-07-19-workload-view-design.md
 *
 * myWork() là góc nhìn cá nhân của cùng dữ liệu — chỉ việc của
 * người đang đăng nhập, không có khối "Chưa phân công".
 * Spec: docs/superpowers/specs/2026-07-20-my-work-page-design.md
 */
class WorkloadPageController extends Controller
{
    public function index(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name']);

        $items = $this->collectOpenItems($tenantId);

        $grouped = $items->groupBy(fn (array $i) => $i['assigned_to'] ?? '__unassigned');

        $blocks = $users
            ->map(function (User $user) use ($grouped): array {
                /** @var Collection<int, array<string, mixed>> $list */
                $list = $grouped->get((string) $user->id, collect())->values();

                return [
                    'user' => $user,
                    'items' => $list,
                    'open_count' => $list->count(),
                    'overdue_count' => $list->where('is_overdue', true)->count(),
                    'blocked_count' => $list->where('is_blocked', true)->count(),
                ];
            })
            ->sortByDesc('open_count')
            ->values();

        $unassigned = $grouped->get('__unassigned', collect())->values();

        return view('app.workload', [
            'blocks' => $blocks,
            'unassigned' => $unassigned,
        ]);
    }

    public function myWork(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;
        $userId = (string) Auth::id();

        $items = $this->collectOpenItems($tenantId)
            ->where('assigned_to', $userId)
            ->values();

        return view('app.my-work', [
            'items' => $items,
            'open_count' => $items->count(),
            'overdue_count' => $items->where('is_overdue', true)->count(),
            'blocked_count' => $items->where('is_blocked', true)->count(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectOpenItems(string $tenantId): Collection
    {
        $today = Carbon::now()->startOfDay();

        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_ON_HOLD])
            ->with('project:id,tenant_id,name')
            ->get(['id', 'tenant_id', 'project_id', 'name', 'title', 'status', 'assigned_to', 'end_date', 'blocked_at']);

        $designItems = DesignItem::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('review_status', [DesignItem::STATUS_APPROVED, DesignItem::STATUS_FINAL])
            ->with('project:id,tenant_id,name')
            ->get(['id', 'tenant_id', 'project_id', 'name', 'review_status', 'assigned_to', 'blocked_at']);

        $items = collect();

        foreach ($tasks as $task) {
            $isOverdue = $task->end_date !== null
                && Carbon::parse(substr((string) $task->end_date, 0, 10))->startOfDay()->lt($today);

            $items->push([
                'assigned_to' => $task->assigned_to !== null ? (string) $task->assigned_to : null,
                'kind_label' => 'Công việc',
                'name' => (string) ($task->name ?? $task->title ?? $task->id),
                'project_name' => $task->project?->name ?? '—',
                'end_date' => $task->end_date,
                'is_overdue' => $isOverdue,
                'is_blocked' => $task->blocked_at !== null,
                'status' => (string) $task->status,
                'url' => route('app.tasks.show', $task->id),
            ]);
        }

        foreach ($designItems as $designItem) {
            $items->push([
                'assigned_to' => $designItem->assigned_to !== null ? (string) $designItem->assigned_to : null,
                'kind_label' => 'Hạng mục thiết kế',
                'name' => (string) $designItem->name,
                'project_name' => $designItem->project?->name ?? '—',
                'end_date' => null,
                'is_overdue' => false,
                'is_blocked' => $designItem->blocked_at !== null,
                'status' => (string) $designItem->review_status,
                'url' => route('operator.design-items.show', $designItem->id),
            ]);
        }

        return $items;
    }
}
