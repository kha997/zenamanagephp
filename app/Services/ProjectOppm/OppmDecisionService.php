<?php

declare(strict_types=1);

namespace App\Services\ProjectOppm;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectOppm\OppmDecision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GAP-110 (Gate 2 D-1, E-1) — management decisions per project. Open decisions can be edited; deciding needs an
 * outcome, cancelling needs a reason (kept as the outcome). Writes need OppmOverlayService::canEdit and are audited.
 */
class OppmDecisionService
{
    public const MAX_ON_PAGE = 5;

    public function __construct(private readonly OppmOverlayService $overlay)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Project $project, User $actor, array $data): OppmDecision
    {
        $this->requireEdit($actor, $project);
        $values = $this->validated($project, $data);

        return DB::transaction(function () use ($project, $actor, $values): OppmDecision {
            $decision = OppmDecision::query()->create($values + [
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'status' => OppmDecision::STATUS_OPEN,
                'created_by' => (string) $actor->id,
            ]);
            $this->audit($actor, $project, $decision, 'created', null, $this->snapshot($decision));

            return $decision;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Project $project, User $actor, OppmDecision $decision, array $data): OppmDecision
    {
        $this->requireEdit($actor, $project);
        $this->requireOf($project, $decision);
        $values = $this->validated($project, $data);

        return $this->onOpen($decision, function (OppmDecision $locked) use ($project, $actor, $values): OppmDecision {
            $before = $this->snapshot($locked);
            $locked->fill($values);
            $locked->save();
            $this->audit($actor, $project, $locked, 'updated', $before, $this->snapshot($locked));

            return $locked;
        });
    }

    public function decide(Project $project, User $actor, OppmDecision $decision, string $outcome): OppmDecision
    {
        $this->requireEdit($actor, $project);
        $this->requireOf($project, $decision);
        $outcome = trim($outcome);
        if ($outcome === '') {
            throw new OppmRuleViolation('Ghi đã quyết phải nhập kết quả.', 'outcome');
        }

        return $this->close($project, $actor, $decision, OppmDecision::STATUS_DECIDED, $outcome, 'decided');
    }

    public function cancel(Project $project, User $actor, OppmDecision $decision, string $reason): OppmDecision
    {
        $this->requireEdit($actor, $project);
        $this->requireOf($project, $decision);
        $reason = trim($reason);
        if ($reason === '') {
            throw new OppmRuleViolation('Huỷ quyết định phải ghi lý do.', 'reason');
        }

        return $this->close($project, $actor, $decision, OppmDecision::STATUS_CANCELLED, $reason, 'cancelled');
    }

    /**
     * Open decisions for the OPPM page: overdue first, then by due date (no date last), at most five; plus the total.
     *
     * @return array{open: list<array<string, mixed>>, open_total: int}
     */
    public function forPage(Project $project, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now())->copy()->startOfDay();
        $base = DB::table('oppm_decisions')
            ->where('oppm_decisions.tenant_id', (string) $project->tenant_id)
            ->where('oppm_decisions.project_id', (string) $project->id)
            ->where('oppm_decisions.status', OppmDecision::STATUS_OPEN);
        $total = (clone $base)->count();
        $rows = $base->leftJoin('users', 'users.id', '=', 'oppm_decisions.decision_maker_id')
            ->orderByRaw('CASE WHEN oppm_decisions.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('oppm_decisions.due_date')
            ->orderBy('oppm_decisions.created_at')
            ->limit(self::MAX_ON_PAGE)
            ->get(['oppm_decisions.id', 'oppm_decisions.title', 'oppm_decisions.due_date', 'users.name as maker_name']);

        return [
            'open' => $rows->map(static function ($row) use ($today): array {
                $due = data_get($row, 'due_date') === null ? null : substr((string) data_get($row, 'due_date'), 0, 10);

                return [
                    'id' => (string) data_get($row, 'id'),
                    'title' => (string) data_get($row, 'title'),
                    'maker' => data_get($row, 'maker_name'),
                    'due_date' => $due,
                    'overdue' => $due !== null && $due < $today->toDateString(),
                ];
            })->values()->all(),
            'open_total' => $total,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, OppmDecision>
     */
    public function allForProject(Project $project): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, OppmDecision> $rows */
        $rows = OppmDecision::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        return $rows;
    }

    public function find(Project $project, string $id): ?OppmDecision
    {
        /** @var OppmDecision|null $decision */
        $decision = OppmDecision::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->first();

        return $decision;
    }

    private function close(Project $project, User $actor, OppmDecision $decision, string $status, string $text, string $event): OppmDecision
    {
        return $this->onOpen($decision, function (OppmDecision $locked) use ($project, $actor, $status, $text, $event): OppmDecision {
            $before = $this->snapshot($locked);
            $locked->status = $status;
            $locked->outcome = mb_substr($text, 0, 2000);
            $locked->decided_at = Carbon::now();
            $locked->decided_by = (string) $actor->id;
            $locked->save();
            $this->audit($actor, $project, $locked, $event, $before, $this->snapshot($locked));

            return $locked;
        });
    }

    /**
     * @param callable(OppmDecision): OppmDecision $action
     */
    private function onOpen(OppmDecision $decision, callable $action): OppmDecision
    {
        return DB::transaction(function () use ($decision, $action): OppmDecision {
            /** @var OppmDecision $locked */
            $locked = OppmDecision::query()->withoutGlobalScopes()->whereKey($decision->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OppmDecision::STATUS_OPEN) {
                throw new OppmRuleViolation('Chỉ sửa / quyết / huỷ được quyết định đang chờ.', 'status');
            }

            return $action($locked);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string|null>
     */
    private function validated(Project $project, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new OppmRuleViolation('Nội dung cần quyết là bắt buộc.', 'title');
        }
        $maker = trim((string) ($data['decision_maker_id'] ?? ''));
        if ($maker !== '' && !User::query()->whereKey($maker)->where('tenant_id', (string) $project->tenant_id)->exists()) {
            throw new OppmRuleViolation('Người quyết phải thuộc công ty.', 'decision_maker_id');
        }
        $due = trim((string) ($data['due_date'] ?? ''));
        if ($due !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $due);
            if ($parsed === false || $parsed->format('Y-m-d') !== $due) {
                throw new OppmRuleViolation('Hạn không hợp lệ.', 'due_date');
            }
        }
        $context = trim((string) ($data['context'] ?? ''));

        return [
            'title' => mb_substr($title, 0, 255),
            'context' => $context === '' ? null : mb_substr($context, 0, 2000),
            'decision_maker_id' => $maker === '' ? null : $maker,
            'due_date' => $due === '' ? null : $due,
        ];
    }

    private function requireEdit(User $actor, Project $project): void
    {
        if (!$this->overlay->canEdit($actor, $project)) {
            throw new OppmRuleViolation('Bạn không có quyền sửa OPPM của dự án này.', 'project');
        }
    }

    private function requireOf(Project $project, OppmDecision $decision): void
    {
        if ((string) $decision->project_id !== (string) $project->id || (string) $decision->tenant_id !== (string) $project->tenant_id) {
            throw new OppmRuleViolation('Quyết định không thuộc dự án này.', 'decision');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(OppmDecision $decision): array
    {
        return [
            'title' => $decision->title,
            'context' => $decision->context,
            'decision_maker_id' => $decision->decision_maker_id,
            'due_date' => $decision->due_date?->toDateString(),
            'status' => $decision->status,
            'outcome' => $decision->outcome,
        ];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(User $actor, Project $project, OppmDecision $decision, string $event, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'action' => 'oppm.decision.' . $event,
            'entity_type' => 'oppm_decision',
            'entity_id' => (string) $decision->id,
            'old_data' => $before,
            'new_data' => $after,
        ]);
    }
}
