<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\OverheadAllocation;
use App\Models\Payroll\OverheadCostLine;
use App\Models\Payroll\OverheadPeriod;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GAP-114 (Gate 2 N-1, C-1, P-1, A-1) — monthly company overhead.
 *
 * - N-1: payroll.manage enters cost lines per month while the month is draft.
 * - C-1: payroll.close closes the month once its payroll is closed. The close stores the split; reopen (reason
 *   required) removes it. Close locks the payroll month row first, then the overhead row, the same order as
 *   PayrollService::reopen, which refuses while overhead is closed.
 * - P-1: pool = cost lines + the GAP-113 labour cost outside projects for that month.
 * - A-1: split by approved project minutes (GAP-113 rows), whole đồng by largest remainder; cents, or the whole pool
 *   when the month has no project minutes, stay in the row without project.
 * - Every write runs in a transaction and is audited.
 */
class OverheadService
{
    public function __construct(private readonly LabourCostService $labour)
    {
    }

    public function canManage(User $actor): bool
    {
        return $actor->hasPermission('payroll.manage');
    }

    public function canClose(User $actor): bool
    {
        return $actor->hasPermission('payroll.close');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addLine(User $actor, string $month, array $data): OverheadCostLine
    {
        $this->requireManage($actor);
        self::requireMonth($month);
        $values = self::validated($data);
        $tenantId = (string) $actor->tenant_id;

        return DB::transaction(function () use ($actor, $month, $values, $tenantId): OverheadCostLine {
            OverheadPeriod::query()->withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'period' => $month],
                ['status' => OverheadPeriod::STATUS_DRAFT],
            );
            /** @var OverheadPeriod $period */
            $period = OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('period', $month)->lockForUpdate()->firstOrFail();
            self::requireDraft($period);
            $line = OverheadCostLine::query()->create($values + [
                'tenant_id' => $tenantId,
                'overhead_period_id' => (string) $period->id,
                'created_by' => (string) $actor->id,
            ]);
            $this->audit($actor, 'overhead.line.added', 'overhead_cost_line', (string) $line->id, null, $values + ['period' => $month]);

            return $line;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateLine(User $actor, OverheadCostLine $line, array $data): OverheadCostLine
    {
        $this->requireManage($actor);
        $this->requireTenant($actor, $line);
        $values = self::validated($data);

        return DB::transaction(function () use ($actor, $line, $values): OverheadCostLine {
            self::requireDraft($this->lockPeriod((string) $line->overhead_period_id));
            /** @var OverheadCostLine $locked */
            $locked = OverheadCostLine::query()->withoutGlobalScopes()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $before = ['category' => $locked->category, 'description' => $locked->description, 'amount' => (string) $locked->amount];
            $locked->fill($values);
            $locked->save();
            $this->audit($actor, 'overhead.line.updated', 'overhead_cost_line', (string) $locked->id, $before, $values);

            return $locked;
        });
    }

    public function deleteLine(User $actor, OverheadCostLine $line): void
    {
        $this->requireManage($actor);
        $this->requireTenant($actor, $line);
        DB::transaction(function () use ($actor, $line): void {
            self::requireDraft($this->lockPeriod((string) $line->overhead_period_id));
            /** @var OverheadCostLine $locked */
            $locked = OverheadCostLine::query()->withoutGlobalScopes()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $before = ['category' => $locked->category, 'description' => $locked->description, 'amount' => (string) $locked->amount];
            $locked->delete();
            $this->audit($actor, 'overhead.line.deleted', 'overhead_cost_line', (string) $line->id, $before, null);
        });
    }

    public function close(User $actor, OverheadPeriod $period): OverheadPeriod
    {
        $this->requireClose($actor);
        $this->requireTenant($actor, $period);

        return DB::transaction(function () use ($actor, $period): OverheadPeriod {
            // Lock order: payroll month row, then the overhead row (as PayrollService::reopen).
            /** @var PayrollPeriod|null $payroll */
            $payroll = PayrollPeriod::query()->withoutGlobalScopes()
                ->where('tenant_id', (string) $period->tenant_id)->where('period', $period->period)->lockForUpdate()->first();
            if ($payroll === null || !$payroll->isClosed()) {
                throw new PayrollRuleViolation('Chưa chốt lương tháng ' . $period->period . '; chốt lương trước khi chốt chi phí chung.', 'period');
            }
            $locked = $this->lockPeriod((string) $period->id);
            self::requireDraft($locked);
            $result = $this->compute($locked);
            OverheadAllocation::query()->withoutGlobalScopes()->where('overhead_period_id', (string) $locked->id)->delete();
            foreach ($result['rows'] as $row) {
                OverheadAllocation::query()->create($row + ['tenant_id' => (string) $locked->tenant_id, 'overhead_period_id' => (string) $locked->id]);
            }
            $locked->status = OverheadPeriod::STATUS_CLOSED;
            $locked->closed_at = \Illuminate\Support\Carbon::now();
            $locked->closed_by = (string) $actor->id;
            $locked->save();
            $this->audit($actor, 'overhead.period.closed', 'overhead_period', (string) $locked->id, ['status' => OverheadPeriod::STATUS_DRAFT], [
                'status' => OverheadPeriod::STATUS_CLOSED, 'period' => $locked->period, 'pool' => $result['pool'], 'lines_total' => $result['lines_total'],
                'labour_outside' => $result['labour_outside'], 'unallocated' => $result['unallocated'],
            ]);

            return $locked;
        });
    }

    public function reopen(User $actor, OverheadPeriod $period, string $reason): OverheadPeriod
    {
        $this->requireClose($actor);
        $this->requireTenant($actor, $period);
        $reason = trim($reason);
        if ($reason === '') {
            throw new PayrollRuleViolation('Mở lại chi phí chung bắt buộc ghi lý do.', 'reason');
        }

        return DB::transaction(function () use ($actor, $period, $reason): OverheadPeriod {
            $locked = $this->lockPeriod((string) $period->id);
            if (!$locked->isClosed()) {
                throw new PayrollRuleViolation('Chi phí chung tháng này chưa chốt.', 'reason');
            }
            $deleted = OverheadAllocation::query()->withoutGlobalScopes()->where('overhead_period_id', (string) $locked->id)->delete();
            $locked->status = OverheadPeriod::STATUS_DRAFT;
            $locked->closed_at = null;
            $locked->closed_by = null;
            $locked->save();
            $this->audit($actor, 'overhead.period.reopened', 'overhead_period', (string) $locked->id, ['status' => OverheadPeriod::STATUS_CLOSED], [
                'status' => OverheadPeriod::STATUS_DRAFT, 'reason' => mb_substr($reason, 0, 500), 'allocations_removed' => $deleted,
            ]);

            return $locked;
        });
    }

    /**
     * P-1 / A-1 computed from the current lines and labour figures; nothing is written. A closed payroll month uses its
     * stored labour allocation, a draft one the GAP-113 preview. Fixed number of queries.
     *
     * @return array{
     *     lines_total: string, labour_outside: string, pool: string, unallocated: string,
     *     rows: list<array{project_id: string|null, minutes: int, amount: string}>
     * }
     */
    public function compute(OverheadPeriod $period): array
    {
        $tenantId = (string) $period->tenant_id;
        $lines = 0;
        foreach (DB::table('overhead_cost_lines')->where('tenant_id', $tenantId)->where('overhead_period_id', (string) $period->id)->pluck('amount') as $amount) {
            $lines += PayrollService::toCents((string) $amount);
        }
        [$outside, $minutes] = $this->labourFor($tenantId, $period->period);
        $pool = $lines + $outside;
        $rows = self::split($pool, $minutes);
        $unallocated = 0;
        foreach ($rows as $row) {
            if ($row['project_id'] === null) {
                $unallocated += PayrollService::toCents($row['amount']);
            }
        }

        return [
            'lines_total' => PayrollService::fromCents($lines),
            'labour_outside' => PayrollService::fromCents($outside),
            'pool' => PayrollService::fromCents($pool),
            'unallocated' => PayrollService::fromCents($unallocated),
            'rows' => $rows,
        ];
    }

    public function periodFor(string $tenantId, string $month): ?OverheadPeriod
    {
        /** @var OverheadPeriod|null $period */
        $period = OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('period', $month)->first();

        return $period;
    }

    /**
     * @return Collection<int, OverheadCostLine>
     */
    public function linesOf(OverheadPeriod $period): Collection
    {
        /** @var Collection<int, OverheadCostLine> $lines */
        $lines = OverheadCostLine::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $period->tenant_id)
            ->where('overhead_period_id', (string) $period->id)
            ->orderBy('created_at')
            ->get();

        return $lines;
    }

    public static function requireMonth(string $month): void
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new PayrollRuleViolation('Tháng không hợp lệ (định dạng YYYY-MM).', 'month');
        }
    }

    /**
     * Labour outside projects (cents), approved project minutes and labour cost per project (cents) for the month: the
     * stored GAP-113 allocation when the payroll month is closed, its preview when draft.
     *
     * @return array{0: int, 1: array<string, int>, 2: array<string, int>, 3: bool}
     */
    public function labourFor(string $tenantId, string $month): array
    {
        /** @var PayrollPeriod|null $payroll */
        $payroll = PayrollPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('period', $month)->first();
        if ($payroll === null) {
            return [0, [], [], false];
        }
        if ($payroll->isClosed()) {
            $rows = DB::table('labour_cost_allocations')->where('tenant_id', $tenantId)->where('payroll_period_id', (string) $payroll->id)
                ->get(['project_id', 'minutes', 'amount'])
                ->map(static fn ($row): array => [
                    'project_id' => data_get($row, 'project_id') === null ? null : (string) data_get($row, 'project_id'),
                    'minutes' => (int) data_get($row, 'minutes'),
                    'amount' => (string) data_get($row, 'amount'),
                ])->all();
        } else {
            $rows = $this->labour->compute($payroll)['rows'];
        }
        $outside = 0;
        $minutes = [];
        $amounts = [];
        foreach ($rows as $row) {
            if ($row['project_id'] === null) {
                $outside += PayrollService::toCents($row['amount']);
                continue;
            }
            $amounts[$row['project_id']] = ($amounts[$row['project_id']] ?? 0) + PayrollService::toCents($row['amount']);
            if ($row['minutes'] > 0) {
                $minutes[$row['project_id']] = ($minutes[$row['project_id']] ?? 0) + $row['minutes'];
            }
        }
        ksort($minutes);

        return [$outside, $minutes, $amounts, $payroll->isClosed()];
    }

    /**
     * @param array<string, int> $minutes
     * @return list<array{project_id: string|null, minutes: int, amount: string}>
     */
    private static function split(int $poolCents, array $minutes): array
    {
        $totalMinutes = array_sum($minutes);
        if ($totalMinutes <= 0 || $poolCents <= 0) {
            return $poolCents === 0 ? [] : [['project_id' => null, 'minutes' => 0, 'amount' => PayrollService::fromCents($poolCents)]];
        }
        $dong = intdiv($poolCents, 100);
        $shares = [];
        $remainders = [];
        $given = 0;
        foreach ($minutes as $projectId => $projectMinutes) {
            $shares[$projectId] = intdiv($dong * $projectMinutes, $totalMinutes);
            $remainders[$projectId] = ($dong * $projectMinutes) % $totalMinutes;
            $given += $shares[$projectId];
        }
        $order = array_keys($remainders);
        usort($order, static fn ($a, $b): int => [$remainders[$b], (string) $a] <=> [$remainders[$a], (string) $b]);
        for ($i = 0, $left = $dong - $given; $i < $left; $i++) {
            $shares[$order[$i]]++;
        }
        $rows = [];
        foreach ($minutes as $projectId => $projectMinutes) {
            $rows[] = ['project_id' => (string) $projectId, 'minutes' => $projectMinutes, 'amount' => PayrollService::fromCents($shares[$projectId] * 100)];
        }
        $rest = $poolCents - $dong * 100;
        if ($rest !== 0) {
            $rows[] = ['project_id' => null, 'minutes' => 0, 'amount' => PayrollService::fromCents($rest)];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{category: string, description: string, amount: string}
     */
    private static function validated(array $data): array
    {
        $category = trim((string) ($data['category'] ?? ''));
        if (!array_key_exists($category, OverheadCostLine::CATEGORIES)) {
            throw new PayrollRuleViolation('Loại chi phí không hợp lệ.', 'category');
        }
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            throw new PayrollRuleViolation('Mô tả là bắt buộc.', 'description');
        }
        $amount = trim((string) ($data['amount'] ?? ''));
        if (preg_match('/^\d{1,13}(\.\d{1,2})?$/', $amount) !== 1) {
            throw new PayrollRuleViolation('Số tiền không hợp lệ.', 'amount');
        }
        if (PayrollService::toCents($amount) <= 0) {
            throw new PayrollRuleViolation('Số tiền phải lớn hơn 0.', 'amount');
        }

        return ['category' => $category, 'description' => mb_substr($description, 0, 255), 'amount' => PayrollService::fromCents(PayrollService::toCents($amount))];
    }

    private function lockPeriod(string $id): OverheadPeriod
    {
        /** @var OverheadPeriod $period */
        $period = OverheadPeriod::query()->withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $period;
    }

    private static function requireDraft(OverheadPeriod $period): void
    {
        if ($period->isClosed()) {
            throw new PayrollRuleViolation('Chi phí chung tháng ' . $period->period . ' đã chốt; mở lại trước khi sửa.', 'period');
        }
    }

    private function requireManage(User $actor): void
    {
        if (!$this->canManage($actor)) {
            throw new PayrollRuleViolation('Bạn không có quyền nhập chi phí chung.', 'period');
        }
    }

    private function requireClose(User $actor): void
    {
        if (!$this->canClose($actor)) {
            throw new PayrollRuleViolation('Bạn không có quyền chốt / mở lại chi phí chung.', 'period');
        }
    }

    private function requireTenant(User $actor, OverheadPeriod|OverheadCostLine $model): void
    {
        if ((string) $model->tenant_id !== (string) $actor->tenant_id) {
            throw new PayrollRuleViolation('Chi phí chung không thuộc công ty.', 'period');
        }
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function audit(User $actor, string $action, string $entityType, string $entityId, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $actor->tenant_id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => $before,
            'new_data' => $after,
        ]);
    }
}
