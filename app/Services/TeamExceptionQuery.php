<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodaySectionResult;
use App\Support\Today\TodayTeamMemberSummary;
use App\Support\Work\OpenWorkItem;
use Illuminate\Support\Collection;

/**
 * "Khối lượng công việc đã ghi nhận" — không phải capacity/availability.
 * Chỉ hiển thị cho actor là PM (Project.pm_id) hoặc team lead (Team.team_lead_id).
 * Nhận $openWork đã fetch sẵn — không tự gọi OpenWorkReadQuery::collect()
 * (tránh gọi lại cùng 2 truy vấn nhiều lần trong 1 request Today).
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.6
 */
class TeamExceptionQuery
{
    private const LIMIT = 10;

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function build(string $tenantId, string $actorId, Collection $openWork): ?TodaySectionResult
    {
        $managedProjectIds = Project::query()
            ->where('tenant_id', $tenantId)
            ->where('pm_id', $actorId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $ledTeams = Team::query()
            ->where('tenant_id', $tenantId)
            ->where('team_lead_id', $actorId)
            ->with('activeMembers:id,name')
            ->get();

        if ($managedProjectIds === [] && $ledTeams->isEmpty()) {
            return null;
        }

        $memberIds = $ledTeams
            ->flatMap(fn (Team $team) => $team->activeMembers->pluck('id'))
            ->map(fn ($id) => (string) $id)
            ->unique();

        $memberNames = $ledTeams
            ->flatMap(fn (Team $team) => $team->activeMembers)
            ->unique('id')
            ->pluck('name', 'id');

        $relevant = $openWork
            ->filter(function (OpenWorkItem $item) use ($managedProjectIds, $memberIds) {
                $inManagedProject = $item->projectId !== null && in_array($item->projectId, $managedProjectIds, true);
                $isTeamMember = $item->assignedTo !== null && $memberIds->contains($item->assignedTo);

                return $inManagedProject || $isTeamMember;
            })
            ->filter(fn (OpenWorkItem $item) => $item->assignedTo !== null);

        $grouped = $relevant->groupBy(fn (OpenWorkItem $item) => $item->assignedTo);

        // Tra tên 1 lần cho mọi assignedTo chưa có tên từ activeMembers — không
        // gọi User::find() bên trong vòng lặp/map() (tránh N+1 theo số thành viên).
        $missingNameIds = $grouped->keys()->diff($memberNames->keys())->values()->all();
        $fetchedNames = $missingNameIds === []
            ? collect()
            : User::query()->whereIn('id', $missingNameIds)->pluck('name', 'id');
        $allNames = $memberNames->union($fetchedNames);

        $items = $grouped
            ->map(fn (Collection $group, string $userId) => new TodayTeamMemberSummary(
                userId: $userId,
                userName: $allNames->get($userId) ?? '—',
                openCount: $group->count(),
                overdueCount: $group->where('isOverdue', true)->count(),
                blockedCount: $group->where('isBlocked', true)->count(),
            ))
            ->sortByDesc('openCount')
            ->take(self::LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult(
            $items,
            $items === [] ? Availability::NO_DATA : Availability::AVAILABLE,
            Reliability::RELIABLE,
            null,
        );
    }
}
