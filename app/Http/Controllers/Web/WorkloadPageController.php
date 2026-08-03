<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OpenWorkReadQuery;
use App\Support\Work\OpenWorkItem;
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
    public function __construct(private readonly OpenWorkReadQuery $openWorkReadQuery)
    {
    }

    public function index(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name']);

        $items = $this->openWorkReadQuery->collect($tenantId);

        $grouped = $items->groupBy(fn (OpenWorkItem $i) => $i->assignedTo ?? '__unassigned');

        $blocks = $users
            ->map(function (User $user) use ($grouped): array {
                /** @var Collection<int, OpenWorkItem> $list */
                $list = $grouped->get((string) $user->id, collect())->values();

                return [
                    'user' => $user,
                    'items' => $list,
                    'open_count' => $list->count(),
                    'overdue_count' => $list->where('isOverdue', true)->count(),
                    'blocked_count' => $list->where('isBlocked', true)->count(),
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

        $items = $this->openWorkReadQuery->collect($tenantId)
            ->where('assignedTo', $userId)
            ->values();

        return view('app.my-work', [
            'items' => $items,
            'open_count' => $items->count(),
            'overdue_count' => $items->where('isOverdue', true)->count(),
            'blocked_count' => $items->where('isBlocked', true)->count(),
        ]);
    }
}
