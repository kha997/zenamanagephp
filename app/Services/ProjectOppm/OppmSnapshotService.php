<?php

declare(strict_types=1);

namespace App\Services\ProjectOppm;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectOppm\OppmSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * GAP-110 (Gate 2 K-1) — period reports: the whole OPPM page data (header, KPIs, work groups with overlay, open
 * decisions, attention lists, finance) normalized to JSON and stored append-only with its sha256. Comparison reads
 * the numbers already stored in two snapshots; nothing is recomputed.
 */
class OppmSnapshotService
{
    public function __construct(
        private readonly ProjectOppmReadService $oppm,
        private readonly OppmOverlayService $overlay,
    ) {
    }

    public function close(Project $project, User $actor, string $label): OppmSnapshot
    {
        if (!$this->overlay->canEdit($actor, $project)) {
            throw new OppmRuleViolation('Bạn không có quyền chốt báo cáo OPPM của dự án này.', 'period_label');
        }
        $label = trim($label);
        if ($label === '') {
            throw new OppmRuleViolation('Tên kỳ là bắt buộc.', 'period_label');
        }

        $data = $this->oppm->forProject($project);
        $data['generated_at'] = $data['generated_at']->format('d/m/Y H:i');
        // Same encoding as the model's array cast, so the stored text and the hash always agree.
        $payload = (array) json_decode((string) json_encode([
            'schema' => 1,
            'header' => $this->oppm->header($project),
            'oppm' => $data,
        ], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($project, $actor, $label, $payload): OppmSnapshot {
            $snapshot = OppmSnapshot::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'period_label' => mb_substr($label, 0, 100),
                'payload' => $payload,
                'payload_hash' => hash('sha256', (string) json_encode($payload)),
                'created_by' => (string) $actor->id,
            ]);
            AuditLog::query()->create([
                'user_id' => (string) $actor->id,
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'action' => 'oppm.snapshot.closed',
                'entity_type' => 'oppm_snapshot',
                'entity_id' => (string) $snapshot->id,
                'old_data' => null,
                'new_data' => ['period_label' => $snapshot->period_label, 'payload_hash' => $snapshot->payload_hash],
            ]);

            return $snapshot;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, OppmSnapshot>
     */
    public function history(Project $project): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, OppmSnapshot> $rows */
        $rows = OppmSnapshot::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'tenant_id', 'project_id', 'period_label', 'payload_hash', 'created_by', 'created_at']);

        return $rows;
    }

    public function find(Project $project, string $id): ?OppmSnapshot
    {
        /** @var OppmSnapshot|null $snapshot */
        $snapshot = OppmSnapshot::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->first();

        return $snapshot;
    }

    public function previous(OppmSnapshot $snapshot): ?OppmSnapshot
    {
        /** @var OppmSnapshot|null $previous */
        $previous = OppmSnapshot::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $snapshot->tenant_id)
            ->where('project_id', (string) $snapshot->project_id)
            ->whereKeyNot($snapshot->id)
            ->where(static fn ($q) => $q->where('created_at', '<', $snapshot->created_at)
                ->orWhere(static fn ($q2) => $q2->where('created_at', $snapshot->created_at)->where('id', '<', (string) $snapshot->id)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $previous;
    }

    /**
     * @return array<string, mixed>
     */
    public function compare(OppmSnapshot $snapshot): array
    {
        $previous = $this->previous($snapshot);
        $after = $snapshot->payload;
        $before = $previous?->payload;
        $pair = static function (?array $old, array $new, string $path): array {
            $a = $old === null ? null : data_get($old, $path);
            $b = data_get($new, $path);

            return [
                'before' => $a,
                'after' => $b,
                'delta' => is_numeric($a) && is_numeric($b) ? $b - $a : null,
            ];
        };

        $oldGroups = [];
        foreach ((array) data_get($before, 'oppm.workstreams', []) as $group) {
            $oldGroups[(string) data_get($group, 'id')] = data_get($group, 'progress_percent');
        }
        $groups = [];
        foreach ((array) data_get($after, 'oppm.workstreams', []) as $group) {
            $id = (string) data_get($group, 'id');
            $old = $oldGroups[$id] ?? null;
            $new = data_get($group, 'progress_percent');
            $groups[] = [
                'name' => (string) data_get($group, 'name'),
                'before' => $old,
                'after' => $new,
                'delta' => is_numeric($old) && is_numeric($new) ? $new - $old : null,
            ];
        }

        $finance = [];
        foreach (['budget', 'contract_value', 'cash_received', 'cost_incurred'] as $measure) {
            $finance[$measure] = $pair($before, $after, 'oppm.health.finance.' . $measure . '.value');
        }

        return [
            'current' => ['id' => (string) $snapshot->id, 'label' => $snapshot->period_label, 'closed_at' => $snapshot->created_at],
            'previous' => $previous === null ? null : ['id' => (string) $previous->id, 'label' => $previous->period_label, 'closed_at' => $previous->created_at],
            'progress' => $pair($before, $after, 'oppm.health.progress.value'),
            'delay' => [
                'before' => $before === null ? null : data_get($before, 'oppm.health.schedule.delay'),
                'after' => data_get($after, 'oppm.health.schedule.delay'),
            ],
            'attention' => [
                'blocked_tasks' => $pair($before, $after, 'oppm.health.attention.blocked_tasks'),
                'overdue_tasks' => $pair($before, $after, 'oppm.health.attention.overdue_tasks'),
                'blocked_design_items' => $pair($before, $after, 'oppm.health.attention.blocked_design_items'),
            ],
            'decisions_open' => $pair($before, $after, 'oppm.decisions.open_total'),
            'finance' => $finance,
            'workstreams' => $groups,
        ];
    }
}
