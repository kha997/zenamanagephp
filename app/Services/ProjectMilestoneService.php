<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GAP-117 (Gate 2 A-1, E-1, T-1, O-1) — project milestone authoring.
 *
 * - Edit (E-1): the project's PM (pm_id) or a project.update holder of the same tenant; otherwise AuthorizationException.
 * - Actions (A-1): create, update, complete (date not in the future), reopen (completed / cancelled → pending), cancel
 *   (reason required). No hard delete. A cancelled milestone must be reopened before it can be edited.
 * - Each write locks the milestone row, which must belong to the given project and tenant (ModelNotFoundException
 *   otherwise), and writes an AuditLog entry with before / after values.
 */
class ProjectMilestoneService
{
    private const FIELDS = ['name', 'description', 'target_date', 'completed_date', 'status', 'order', 'cancelled_reason'];

    public function canEdit(User $actor, Project $project): bool
    {
        if ((string) $actor->tenant_id !== (string) $project->tenant_id) {
            return false;
        }

        return (string) $project->getAttribute('pm_id') === (string) $actor->id || $actor->hasPermission('project.update');
    }

    /**
     * @return Collection<int, ProjectMilestone>
     */
    public function listForProject(Project $project): Collection
    {
        return ProjectMilestone::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->orderBy('order')
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Project $project, User $actor, array $data): ProjectMilestone
    {
        $this->requireEdit($actor, $project);
        $fields = $this->validated($data);

        return DB::transaction(function () use ($project, $actor, $fields): ProjectMilestone {
            $milestone = ProjectMilestone::query()->create($fields + [
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'status' => ProjectMilestone::STATUS_PENDING,
                'created_by' => (string) $actor->id,
            ]);
            $this->audit($actor, $project, 'milestone.created', $milestone, null);

            return $milestone;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Project $project, User $actor, string $milestoneId, array $data): ProjectMilestone
    {
        $this->requireEdit($actor, $project);
        $fields = $this->validated($data);

        return $this->change($project, $actor, $milestoneId, 'milestone.updated', function (ProjectMilestone $milestone) use ($fields): void {
            if ($milestone->status === ProjectMilestone::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['status' => 'Mốc đã huỷ, cần mở lại trước khi sửa.']);
            }
            $milestone->fill($fields);
        });
    }

    public function complete(Project $project, User $actor, string $milestoneId, ?string $completedDate): ProjectMilestone
    {
        $this->requireEdit($actor, $project);
        $date = $this->completedDate($completedDate);

        return $this->change($project, $actor, $milestoneId, 'milestone.completed', function (ProjectMilestone $milestone) use ($date): void {
            if ($milestone->status === ProjectMilestone::STATUS_COMPLETED || $milestone->status === ProjectMilestone::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['status' => 'Chỉ đánh dấu xong được mốc đang chờ.']);
            }
            $milestone->status = ProjectMilestone::STATUS_COMPLETED;
            $milestone->completed_date = Carbon::createFromFormat('!Y-m-d', $date);
        });
    }

    public function reopen(Project $project, User $actor, string $milestoneId): ProjectMilestone
    {
        $this->requireEdit($actor, $project);

        return $this->change($project, $actor, $milestoneId, 'milestone.reopened', function (ProjectMilestone $milestone): void {
            if (!in_array($milestone->status, [ProjectMilestone::STATUS_COMPLETED, ProjectMilestone::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages(['status' => 'Chỉ mở lại được mốc đã xong hoặc đã huỷ.']);
            }
            $metadata = $milestone->metadata ?? [];
            unset($metadata['cancelled_reason'], $metadata['cancelled_at']);
            $milestone->status = ProjectMilestone::STATUS_PENDING;
            $milestone->completed_date = null;
            $milestone->metadata = $metadata === [] ? null : $metadata;
        });
    }

    public function cancel(Project $project, User $actor, string $milestoneId, ?string $reason): ProjectMilestone
    {
        $this->requireEdit($actor, $project);
        $reason = trim((string) ($reason ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Cần ghi lý do huỷ mốc.']);
        }
        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Lý do tối đa 500 ký tự.']);
        }

        return $this->change($project, $actor, $milestoneId, 'milestone.cancelled', function (ProjectMilestone $milestone) use ($reason): void {
            if ($milestone->status !== ProjectMilestone::STATUS_PENDING && $milestone->status !== ProjectMilestone::STATUS_OVERDUE) {
                throw ValidationException::withMessages(['status' => 'Chỉ huỷ được mốc đang chờ.']);
            }
            $milestone->status = ProjectMilestone::STATUS_CANCELLED;
            $milestone->metadata = array_merge($milestone->metadata ?? [], [
                'cancelled_reason' => $reason,
                'cancelled_at' => Carbon::now()->toIso8601String(),
            ]);
        });
    }

    /**
     * @param callable(ProjectMilestone): void $mutate
     */
    private function change(Project $project, User $actor, string $milestoneId, string $action, callable $mutate): ProjectMilestone
    {
        return DB::transaction(function () use ($project, $actor, $milestoneId, $action, $mutate): ProjectMilestone {
            /** @var ProjectMilestone $milestone */
            $milestone = ProjectMilestone::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $project->tenant_id)
                ->where('project_id', (string) $project->id)
                ->whereKey($milestoneId)
                ->lockForUpdate()
                ->firstOrFail();
            $before = self::snapshot($milestone);
            $mutate($milestone);
            $milestone->save();
            $this->audit($actor, $project, $action, $milestone, $before);

            return $milestone;
        });
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{name: string, description: string|null, target_date: string, order: int}
     */
    private function validated(array $data): array
    {
        $errors = [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Cần nhập tên mốc.';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'] = 'Tên mốc tối đa 255 ký tự.';
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 2000) {
            $errors['description'] = 'Mô tả tối đa 2000 ký tự.';
        }

        $target = self::date($data['target_date'] ?? null);
        if ($target === null) {
            $errors['target_date'] = 'Cần nhập ngày mục tiêu (YYYY-MM-DD).';
        }

        $orderRaw = trim((string) ($data['order'] ?? ''));
        $order = 0;
        if ($orderRaw !== '') {
            if (!preg_match('/^\d{1,3}$/', $orderRaw)) {
                $errors['order'] = 'Thứ tự là số từ 0 đến 999.';
            } else {
                $order = (int) $orderRaw;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'target_date' => (string) $target,
            'order' => $order,
        ];
    }

    private function completedDate(?string $value): string
    {
        $today = Carbon::today()->toDateString();
        if ($value === null || trim($value) === '') {
            return $today;
        }
        $date = self::date($value);
        if ($date === null) {
            throw ValidationException::withMessages(['completed_date' => 'Ngày xong không hợp lệ (YYYY-MM-DD).']);
        }
        if ($date > $today) {
            throw ValidationException::withMessages(['completed_date' => 'Ngày xong không được ở tương lai.']);
        }

        return $date;
    }

    private static function date(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value ? $value : null;
    }

    private function requireEdit(User $actor, Project $project): void
    {
        if (!$this->canEdit($actor, $project)) {
            throw new AuthorizationException('Bạn không có quyền sửa mốc tiến độ của dự án này.');
        }
    }

    /**
     * @param array<string, mixed>|null $before
     */
    private function audit(User $actor, Project $project, string $action, ProjectMilestone $milestone, ?array $before): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'action' => $action,
            'entity_type' => 'project_milestone',
            'entity_id' => (string) $milestone->id,
            'old_data' => $before,
            'new_data' => self::snapshot($milestone),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(ProjectMilestone $milestone): array
    {
        $row = [];
        foreach (self::FIELDS as $field) {
            $row[$field] = match ($field) {
                'target_date', 'completed_date' => $milestone->{$field}?->toDateString(),
                'cancelled_reason' => $milestone->metadata['cancelled_reason'] ?? null,
                default => $milestone->{$field},
            };
        }

        return $row;
    }
}
