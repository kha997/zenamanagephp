<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Treasury\TreasuryPeriodLock as Lock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GAP-107 (Gate 2 L-1, U-1, R-1) — per-project Treasury period lock.
 *
 * L-1: once a project is locked through date D, no new ledger posting may carry a date on or before D (funding, owner
 * contribution, transfer, adjustment, reversal, expense / advance approval, advance return, settlement, route leg).
 * Reversing an old document stays possible when the reversal itself is dated after D. R-1: reconciliation is not a
 * posting and stays allowed. U-1: holders of treasury.manage_period_lock may move the date back or clear it, with a
 * reason. Every change is a new history row plus an audit record.
 *
 * Concurrency: lock changes and every posting check take the project row FOR UPDATE first, so a posting either commits
 * before a lock that covers it or sees that lock.
 */
class TreasuryPeriodLockService
{
    public function lockedThrough(Project $project): ?Carbon
    {
        $date = Lock::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->value('locked_through');

        return $date === null ? null : Carbon::parse((string) $date)->startOfDay();
    }

    /**
     * @return list<Lock>
     */
    public function history(Project $project): array
    {
        /** @var list<Lock> $rows */
        $rows = Lock::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(50)
            ->get()
            ->values()
            ->all();

        return $rows;
    }

    public function canManage(User $actor): bool
    {
        return $actor->hasPermission('treasury.manage_period_lock');
    }

    /** L-1: move the lock forward to $through (inclusive). */
    public function lock(Project $project, User $actor, string $through, ?string $reason = null): Lock
    {
        $date = $this->parse($through, 'locked_through');
        if ($date->greaterThan(Carbon::today())) {
            throw new TreasuryRuleViolation('Không khoá kỳ ở tương lai.', 'locked_through');
        }

        return $this->change($project, $actor, function (?Carbon $current) use ($date): void {
            if ($current !== null && !$date->greaterThan($current)) {
                throw new TreasuryRuleViolation('Khoá kỳ chỉ tiến lên; muốn lùi mốc hãy dùng "Mở khoá".', 'locked_through');
            }
        }, $date, $reason, 'treasury.period.locked');
    }

    /** U-1: move the lock back to $through, or clear it (null), with a reason. */
    public function unlock(Project $project, User $actor, ?string $through, string $reason): Lock
    {
        if (trim($reason) === '') {
            throw new TreasuryRuleViolation('Mở khoá bắt buộc ghi lý do.', 'reason');
        }
        $date = $through === null || trim($through) === '' ? null : $this->parse($through, 'locked_through');

        return $this->change($project, $actor, function (?Carbon $current) use ($date): void {
            if ($current === null) {
                throw new TreasuryRuleViolation('Dự án chưa khoá kỳ nào.', 'locked_through');
            }
            if ($date !== null && !$date->lessThan($current)) {
                throw new TreasuryRuleViolation('Mở khoá phải lùi mốc về ngày sớm hơn mốc hiện tại.', 'locked_through');
            }
        }, $date, $reason, 'treasury.period.unlocked');
    }

    /**
     * L-1: refuse a posting dated on or before the lock. Call it first inside the posting transaction.
     */
    public function requireOpen(Project $project, mixed $date, string $field = 'transaction_date'): void
    {
        $this->lockProjectRow($project);
        $through = $this->lockedThrough($project);
        if ($through === null) {
            return;
        }
        $day = Carbon::parse(substr((string) ($date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date), 0, 10))->startOfDay();
        if (!$day->greaterThan($through)) {
            throw new TreasuryRuleViolation('Kỳ đến hết ngày ' . $through->format('d/m/Y') . ' đã khoá; không ghi sổ được vào ngày ' . $day->format('d/m/Y') . '.', $field);
        }
    }

    /**
     * @param \Closure(?Carbon): void $guard
     */
    private function change(Project $project, User $actor, \Closure $guard, ?Carbon $date, ?string $reason, string $action): Lock
    {
        if (!$this->canManage($actor)) {
            throw new TreasuryRuleViolation('Bạn không có quyền khoá / mở kỳ.', 'locked_through');
        }

        return DB::transaction(function () use ($project, $actor, $guard, $date, $reason, $action): Lock {
            $this->lockProjectRow($project);
            $current = $this->lockedThrough($project);
            $guard($current);
            $lock = Lock::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'locked_through' => $date?->toDateString(),
                'reason' => $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 500),
                'created_by' => (string) $actor->id,
            ]);
            AuditLog::query()->create([
                'user_id' => (string) $actor->id,
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'action' => $action,
                'entity_type' => 'treasury_period_lock',
                'entity_id' => (string) $lock->id,
                'old_data' => ['locked_through' => $current?->toDateString()],
                'new_data' => ['locked_through' => $date?->toDateString(), 'reason' => $lock->reason],
            ]);

            return $lock;
        });
    }

    private function lockProjectRow(Project $project): void
    {
        if (DB::transactionLevel() > 0) {
            DB::table('projects')->where('id', (string) $project->id)->lockForUpdate()->value('id');
        }
    }

    private function parse(string $value, string $field): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            throw new TreasuryRuleViolation('Ngày không hợp lệ.', $field);
        }

        return Carbon::parse(trim($value))->startOfDay();
    }
}
