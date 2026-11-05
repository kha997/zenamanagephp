<?php

declare(strict_types=1);

namespace App\Services\ProjectOppm;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectOppm\OppmProjectNote;
use App\Models\ProjectOppm\OppmWorkstreamNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * GAP-110 (Gate 2 A-1, W-1, E-1) — the 20% management data PM / Owner add to OPPM: per root component (outcome, weight,
 * next action, note, display order) and per project (summary, next action). Weight is display-only. Edits by the
 * project's PM or project.update holders of the same tenant; every save is audited with old and new values. Reads use
 * a fixed number of queries.
 */
class OppmOverlayService
{
    private const WORKSTREAM_FIELDS = ['outcome', 'weight', 'next_action', 'next_action_owner_id', 'next_action_due', 'note', 'display_order'];

    private const PROJECT_FIELDS = ['summary', 'next_action', 'next_action_owner_id', 'next_action_due'];

    public function canEdit(User $actor, Project $project): bool
    {
        if ((string) $actor->tenant_id !== (string) $project->tenant_id) {
            return false;
        }

        return (string) $project->getAttribute('pm_id') === (string) $actor->id || $actor->hasPermission('project.update');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveWorkstream(Project $project, User $actor, string $componentId, array $data): OppmWorkstreamNote
    {
        $this->requireEdit($actor, $project);
        $isRoot = DB::table('components')
            ->where('id', $componentId)
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereNull('parent_component_id')
            ->exists();
        if (!$isRoot) {
            throw new OppmRuleViolation('Chỉ nhập cho nhóm công việc (hạng mục gốc) của dự án này.', 'component');
        }

        $weight = self::text($data['weight'] ?? null);
        if ($weight !== null && (!is_numeric($weight) || (float) $weight < 0 || (float) $weight > 100)) {
            throw new OppmRuleViolation('Trọng số phải từ 0 đến 100.', 'weight');
        }
        $order = self::text($data['display_order'] ?? null);
        if ($order !== null && (filter_var($order, FILTER_VALIDATE_INT) === false || (int) $order < 0 || (int) $order > 999)) {
            throw new OppmRuleViolation('Thứ tự hiển thị phải là số từ 0 đến 999.', 'display_order');
        }
        $values = [
            'outcome' => self::limited($data['outcome'] ?? null, 500),
            'weight' => $weight === null ? null : number_format((float) $weight, 2, '.', ''),
            'note' => self::limited($data['note'] ?? null, 1000),
            'display_order' => $order === null ? null : (int) $order,
        ] + $this->nextAction($project, $data);

        return DB::transaction(function () use ($project, $actor, $componentId, $values): OppmWorkstreamNote {
            /** @var OppmWorkstreamNote|null $note */
            $note = OppmWorkstreamNote::query()->withoutGlobalScopes()
                ->where('project_id', (string) $project->id)
                ->where('component_id', $componentId)
                ->lockForUpdate()
                ->first();
            $before = $note === null ? null : self::snapshot($note, self::WORKSTREAM_FIELDS);
            $note ??= new OppmWorkstreamNote(['tenant_id' => (string) $project->tenant_id, 'project_id' => (string) $project->id, 'component_id' => $componentId]);
            $note->fill($values + ['updated_by' => (string) $actor->id]);
            $note->save();
            $this->audit($actor, $project, 'oppm.workstream_note.saved', (string) $note->id, $before, self::snapshot($note, self::WORKSTREAM_FIELDS) + ['component_id' => $componentId]);

            return $note;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveProject(Project $project, User $actor, array $data): OppmProjectNote
    {
        $this->requireEdit($actor, $project);
        $values = ['summary' => self::limited($data['summary'] ?? null, 2000)] + $this->nextAction($project, $data);

        return DB::transaction(function () use ($project, $actor, $values): OppmProjectNote {
            /** @var OppmProjectNote|null $note */
            $note = OppmProjectNote::query()->withoutGlobalScopes()->where('project_id', (string) $project->id)->lockForUpdate()->first();
            $before = $note === null ? null : self::snapshot($note, self::PROJECT_FIELDS);
            $note ??= new OppmProjectNote(['tenant_id' => (string) $project->tenant_id, 'project_id' => (string) $project->id]);
            $note->fill($values + ['updated_by' => (string) $actor->id]);
            $note->save();
            $this->audit($actor, $project, 'oppm.project_note.saved', (string) $note->id, $before, self::snapshot($note, self::PROJECT_FIELDS));

            return $note;
        });
    }

    /**
     * Two queries whatever the number of work groups: notes (with owner names) and the project note.
     *
     * @return array{workstreams: array<string, array<string, mixed>>, project: array<string, mixed>|null}
     */
    public function overlayFor(Project $project): array
    {
        $rows = DB::table('oppm_workstream_notes')
            ->leftJoin('users', 'users.id', '=', 'oppm_workstream_notes.next_action_owner_id')
            ->where('oppm_workstream_notes.tenant_id', (string) $project->tenant_id)
            ->where('oppm_workstream_notes.project_id', (string) $project->id)
            ->get(['oppm_workstream_notes.*', 'users.name as owner_name']);
        $workstreams = [];
        foreach ($rows as $row) {
            $workstreams[(string) data_get($row, 'component_id')] = self::present($row, ['outcome', 'note']) + [
                'weight' => data_get($row, 'weight') === null ? null : (float) data_get($row, 'weight'),
                'display_order' => data_get($row, 'display_order') === null ? null : (int) data_get($row, 'display_order'),
            ];
        }

        $project = DB::table('oppm_project_notes')
            ->leftJoin('users', 'users.id', '=', 'oppm_project_notes.next_action_owner_id')
            ->where('oppm_project_notes.tenant_id', (string) $project->tenant_id)
            ->where('oppm_project_notes.project_id', (string) $project->id)
            ->first(['oppm_project_notes.*', 'users.name as owner_name']);

        return [
            'workstreams' => $workstreams,
            'project' => $project === null ? null : self::present($project, ['summary']),
        ];
    }

    /**
     * @param list<string> $textFields
     * @return array<string, mixed>
     */
    private static function present(object $row, array $textFields): array
    {
        $out = [];
        foreach ($textFields as $field) {
            $out[$field] = data_get($row, $field);
        }
        $due = data_get($row, 'next_action_due');

        return $out + [
            'next_action' => data_get($row, 'next_action'),
            'next_action_owner_id' => data_get($row, 'next_action_owner_id'),
            'next_action_owner' => data_get($row, 'owner_name'),
            'next_action_due' => $due === null ? null : substr((string) $due, 0, 10),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{next_action: string|null, next_action_owner_id: string|null, next_action_due: string|null}
     */
    private function nextAction(Project $project, array $data): array
    {
        $owner = self::text($data['next_action_owner_id'] ?? null);
        if ($owner !== null && !User::query()->whereKey($owner)->where('tenant_id', (string) $project->tenant_id)->exists()) {
            throw new OppmRuleViolation('Người làm phải thuộc công ty.', 'next_action_owner_id');
        }
        $due = self::text($data['next_action_due'] ?? null);
        if ($due !== null) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $due);
            if ($parsed === false || $parsed->format('Y-m-d') !== $due) {
                throw new OppmRuleViolation('Hạn không hợp lệ.', 'next_action_due');
            }
        }

        return [
            'next_action' => self::limited($data['next_action'] ?? null, 500),
            'next_action_owner_id' => $owner,
            'next_action_due' => $due,
        ];
    }

    private function requireEdit(User $actor, Project $project): void
    {
        if (!$this->canEdit($actor, $project)) {
            throw new OppmRuleViolation('Bạn không có quyền sửa OPPM của dự án này.', 'project');
        }
    }

    /**
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private static function snapshot(OppmWorkstreamNote|OppmProjectNote $note, array $fields): array
    {
        $row = [];
        foreach ($fields as $field) {
            $value = $note->getAttribute($field);
            $row[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $row;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(User $actor, Project $project, string $action, string $entityId, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'action' => $action,
            'entity_type' => 'oppm',
            'entity_id' => $entityId,
            'old_data' => $before,
            'new_data' => $after,
        ]);
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function limited(mixed $value, int $max): ?string
    {
        $value = self::text($value);

        return $value === null ? null : mb_substr($value, 0, $max);
    }
}
