<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GAP-109 (Gate 2 C-1, B-1, H-1, L-1, P-1) — monthly payroll cost.
 *
 * C-1: pay items are entered; net pay = salary + allowance + overtime + bonus paid − deductions; company cost = salary +
 * allowance + overtime + employer insurance + other company cost + bonus accrual. B-1: the accrual starts at 1/12 of the
 * annual bonus estimate; the bonus actually paid only reaches net pay. H-1: standard hours = working days × hours per
 * day, or the person's own monthly hours; hourly cost = company cost ÷ standard hours, frozen when the month is closed.
 * L-1: payroll.manage edits drafts, payroll.close closes and reopens (reason required). P-1: lines are marked paid after
 * close; no Treasury posting. GAP-113 H-1 / S-1: close is refused while the month has running or waiting hours, and
 * stores the labour cost per project (LabourCostService); reopen removes it. GAP-114 C-1: reopen is refused while the
 * month's overhead is closed. Every write runs in a transaction on the locked period row and is audited.
 */
class PayrollService
{
    private const EARNINGS = ['base_salary', 'allowance', 'overtime', 'bonus_paid'];

    private const DEDUCTIONS = ['employee_insurance', 'personal_income_tax', 'advance_deduction', 'other_deduction'];

    private const COMPANY_COST = ['base_salary', 'allowance', 'overtime', 'employer_insurance', 'other_company_cost', 'bonus_accrual'];

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

    public function createPeriod(User $actor, string $period, int $workingDays, string $hoursPerDay = '8'): PayrollPeriod
    {
        $this->requireManage($actor);
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw new PayrollRuleViolation('Tháng không hợp lệ (định dạng YYYY-MM).', 'period');
        }
        self::requireCalendar($workingDays, $hoursPerDay);
        $tenantId = (string) $actor->tenant_id;
        if (PayrollPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('period', $period)->exists()) {
            throw new PayrollRuleViolation('Bảng lương tháng này đã có.', 'period');
        }
        $first = $period . '-01';
        $last = date('Y-m-t', (int) strtotime($first));

        return DB::transaction(function () use ($actor, $tenantId, $period, $workingDays, $hoursPerDay, $first, $last): PayrollPeriod {
            $model = PayrollPeriod::query()->create([
                'tenant_id' => $tenantId,
                'period' => $period,
                'working_days' => $workingDays,
                'hours_per_day' => self::decimal($hoursPerDay),
                'status' => PayrollPeriod::STATUS_DRAFT,
            ]);
            $staff = StaffMember::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $last)
                ->where(static fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $first))
                ->orderBy('code')
                ->get();
            foreach ($staff as $person) {
                /** @var StaffMember $person */
                $line = new PayrollLine([
                    'tenant_id' => $tenantId,
                    'payroll_period_id' => (string) $model->id,
                    'staff_member_id' => (string) $person->id,
                    'bonus_accrual' => self::fromCents(intdiv(self::toCents((string) $person->annual_bonus_estimate), 12)),
                ]);
                $this->compute($line, $model, $person);
                $line->save();
            }
            $this->audit($actor, 'payroll.period.created', 'payroll_period', (string) $model->id, null, [
                'period' => $period, 'working_days' => $workingDays, 'hours_per_day' => self::decimal($hoursPerDay), 'lines' => $staff->count(),
            ]);

            return $model;
        });
    }

    public function updatePeriod(User $actor, PayrollPeriod $period, int $workingDays, string $hoursPerDay): PayrollPeriod
    {
        $this->requireManage($actor);
        $this->requireTenant($actor, $period);
        self::requireCalendar($workingDays, $hoursPerDay);

        return DB::transaction(function () use ($actor, $period, $workingDays, $hoursPerDay): PayrollPeriod {
            $locked = $this->lockDraft($period);
            $before = ['working_days' => $locked->working_days, 'hours_per_day' => (string) $locked->hours_per_day];
            $locked->working_days = $workingDays;
            $locked->hours_per_day = self::decimal($hoursPerDay);
            $locked->save();
            foreach ($this->linesOf($locked) as $line) {
                $this->compute($line, $locked, $line->staffMember);
                $line->save();
            }
            $this->audit($actor, 'payroll.period.updated', 'payroll_period', (string) $locked->id, $before, [
                'working_days' => $workingDays, 'hours_per_day' => self::decimal($hoursPerDay),
            ]);

            return $locked;
        });
    }

    /**
     * @param array<string, mixed> $data entered items; a missing key keeps its value, an empty one means zero
     */
    public function updateLine(User $actor, PayrollLine $line, array $data): PayrollLine
    {
        $this->requireManage($actor);
        $this->requireTenant($actor, $line);
        $values = [];
        foreach (PayrollLine::INPUT_COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $raw = trim((string) ($data[$column] ?? ''));
            $raw = $raw === '' ? '0' : $raw;
            if (!is_numeric($raw) || preg_match('/^-?\d{1,13}(\.\d{1,2})?$/', $raw) !== 1) {
                throw new PayrollRuleViolation('Số tiền không hợp lệ.', $column);
            }
            if ((float) $raw < 0) {
                throw new PayrollRuleViolation('Số tiền không được âm.', $column);
            }
            $values[$column] = self::decimal($raw);
        }
        $note = array_key_exists('note', $data) ? mb_substr(trim((string) $data['note']), 0, 500) : null;

        return DB::transaction(function () use ($actor, $line, $values, $note, $data): PayrollLine {
            /** @var PayrollPeriod $period */
            $period = PayrollPeriod::query()->withoutGlobalScopes()->whereKey($line->payroll_period_id)->firstOrFail();
            // Compute with the locked row: a concurrent calendar change may have committed while we waited.
            $period = $this->lockDraft($period);
            /** @var PayrollLine $locked */
            $locked = PayrollLine::query()->withoutGlobalScopes()->with('staffMember')->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            $locked->fill($values);
            if (array_key_exists('note', $data)) {
                $locked->note = $note === '' ? null : $note;
            }
            $this->compute($locked, $period, $locked->staffMember);
            $locked->save();
            $this->audit($actor, 'payroll.line.updated', 'payroll_line', (string) $locked->id, $before, $this->snapshot($locked));

            return $locked;
        });
    }

    public function close(User $actor, PayrollPeriod $period): PayrollPeriod
    {
        $this->requireClose($actor);
        $this->requireTenant($actor, $period);

        return DB::transaction(function () use ($actor, $period): PayrollPeriod {
            $locked = $this->lockDraft($period);
            // GAP-113 H-1: hours still running or waiting would be locked out of the labour cost for good.
            $pending = array_sum($this->labour->pendingEntries($locked));
            if ($pending > 0) {
                throw new PayrollRuleViolation('Còn ' . $pending . ' dòng giờ đang chạy / chờ duyệt trong tháng; duyệt hoặc từ chối trước khi chốt.', 'period');
            }
            foreach ($this->linesOf($locked) as $line) {
                $this->compute($line, $locked, $line->staffMember);
                $line->save();
            }
            // GAP-113 S-1: labour cost per project is frozen with the month.
            $this->labour->allocate($actor, $locked);
            $locked->status = PayrollPeriod::STATUS_CLOSED;
            $locked->closed_at = \Illuminate\Support\Carbon::now();
            $locked->closed_by = (string) $actor->id;
            $locked->save();
            $this->audit($actor, 'payroll.period.closed', 'payroll_period', (string) $locked->id, ['status' => PayrollPeriod::STATUS_DRAFT], [
                'status' => PayrollPeriod::STATUS_CLOSED, 'totals' => $this->totals($locked),
            ]);

            return $locked;
        });
    }

    public function reopen(User $actor, PayrollPeriod $period, string $reason): PayrollPeriod
    {
        $this->requireClose($actor);
        $this->requireTenant($actor, $period);
        $reason = trim($reason);
        if ($reason === '') {
            throw new PayrollRuleViolation('Mở lại bảng lương bắt buộc ghi lý do.', 'reason');
        }

        return DB::transaction(function () use ($actor, $period, $reason): PayrollPeriod {
            /** @var PayrollPeriod $locked */
            $locked = PayrollPeriod::query()->withoutGlobalScopes()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if (!$locked->isClosed()) {
                throw new PayrollRuleViolation('Bảng lương chưa chốt.', 'reason');
            }
            // GAP-114 C-1: the overhead split of this month rests on its labour figures; reopen that first.
            $overhead = DB::table('overhead_periods')->where('tenant_id', (string) $locked->tenant_id)->where('period', $locked->period)->sharedLock()->value('status');
            if ($overhead === 'closed') {
                throw new PayrollRuleViolation('Chi phí chung tháng ' . $locked->period . ' đã chốt; mở lại chi phí chung trước khi mở lại lương.', 'reason');
            }
            $this->labour->clear($actor, $locked);
            $locked->status = PayrollPeriod::STATUS_DRAFT;
            $locked->closed_at = null;
            $locked->closed_by = null;
            $locked->save();
            $this->audit($actor, 'payroll.period.reopened', 'payroll_period', (string) $locked->id, ['status' => PayrollPeriod::STATUS_CLOSED], [
                'status' => PayrollPeriod::STATUS_DRAFT, 'reason' => mb_substr($reason, 0, 500),
            ]);

            return $locked;
        });
    }

    public function markPaid(User $actor, PayrollLine $line, string $paidAt, string $reference): PayrollLine
    {
        $this->requireManage($actor);
        $this->requireTenant($actor, $line);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $paidAt);
        if ($parsed === false || $parsed->format('Y-m-d') !== $paidAt) {
            throw new PayrollRuleViolation('Ngày trả không hợp lệ.', 'paid_at');
        }

        return DB::transaction(function () use ($actor, $line, $paidAt, $reference): PayrollLine {
            /** @var PayrollPeriod $period */
            $period = PayrollPeriod::query()->withoutGlobalScopes()->whereKey($line->payroll_period_id)->lockForUpdate()->firstOrFail();
            if (!$period->isClosed()) {
                throw new PayrollRuleViolation('Chỉ đánh dấu đã trả sau khi bảng lương đã chốt.', 'paid_at');
            }
            /** @var PayrollLine $locked */
            $locked = PayrollLine::query()->withoutGlobalScopes()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $before = ['paid_at' => $locked->paid_at?->toDateString(), 'payment_reference' => $locked->payment_reference];
            $reference = trim($reference);
            $locked->paid_at = \Illuminate\Support\Carbon::parse($paidAt);
            $locked->payment_reference = $reference === '' ? null : mb_substr($reference, 0, 100);
            $locked->save();
            $this->audit($actor, 'payroll.line.paid', 'payroll_line', (string) $locked->id, $before, [
                'paid_at' => $paidAt, 'payment_reference' => $locked->payment_reference,
            ]);

            return $locked;
        });
    }

    /**
     * @return Collection<int, PayrollLine>
     */
    public function linesOf(PayrollPeriod $period): Collection
    {
        /** @var Collection<int, PayrollLine> $lines */
        $lines = PayrollLine::query()->withoutGlobalScopes()
            ->with(['staffMember' => static fn ($q) => $q->withoutGlobalScopes()])
            ->where('payroll_period_id', (string) $period->id)
            ->get()
            ->sortBy(static fn (PayrollLine $line): string => (string) $line->staffMember->code)
            ->values();

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    public function totals(PayrollPeriod $period): array
    {
        $totals = array_fill_keys([...PayrollLine::INPUT_COLUMNS, 'net_pay', 'company_cost'], 0);
        foreach ($this->linesOf($period) as $line) {
            foreach (array_keys($totals) as $column) {
                $totals[$column] += self::toCents((string) $line->getAttribute($column));
            }
        }

        return array_map([self::class, 'fromCents'], $totals);
    }

    public function csv(PayrollPeriod $period): string
    {
        $headers = ['Mã', 'Họ tên', 'Loại', 'Khối văn phòng', 'Lương cơ bản', 'Phụ cấp', 'Tăng ca', 'Thưởng thực trả',
            'BH công ty đóng', 'Chi phí khác', 'Thưởng phân bổ', 'BH người lao động', 'Thuế TNCN', 'Trừ tạm ứng',
            'Khấu trừ khác', 'Thực nhận', 'Tổng chi phí công ty', 'Giờ chuẩn', 'Đơn giá giờ', 'Ngày trả', 'Số chứng từ'];
        $out = fopen('php://temp', 'r+');
        assert($out !== false);
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($this->linesOf($period) as $line) {
            $staff = $line->staffMember;
            fputcsv($out, array_map([self::class, 'safeCell'], [
                $staff->code, $staff->name, StaffMember::TYPES[$staff->staff_type] ?? $staff->staff_type, $staff->is_overhead ? 'Có' : 'Không',
                $line->base_salary, $line->allowance, $line->overtime, $line->bonus_paid, $line->employer_insurance,
                $line->other_company_cost, $line->bonus_accrual, $line->employee_insurance, $line->personal_income_tax,
                $line->advance_deduction, $line->other_deduction, $line->net_pay, $line->company_cost, $line->standard_hours,
                $line->hourly_cost, $line->paid_at?->toDateString() ?? '', $line->payment_reference ?? '',
            ]));
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function compute(PayrollLine $line, PayrollPeriod $period, StaffMember $staff): void
    {
        $sum = static function (array $columns) use ($line): int {
            $cents = 0;
            foreach ($columns as $column) {
                $cents += self::toCents((string) ($line->getAttribute($column) ?? '0'));
            }

            return $cents;
        };
        $net = $sum(self::EARNINGS) - $sum(self::DEDUCTIONS);
        $cost = $sum(self::COMPANY_COST);
        $hoursCents = $staff->standard_hours_override !== null
            ? self::toCents((string) $staff->standard_hours_override)
            : (int) $period->working_days * self::toCents((string) $period->hours_per_day);
        $line->net_pay = self::fromCents($net);
        $line->company_cost = self::fromCents($cost);
        $line->standard_hours = self::fromCents($hoursCents);
        // cost (cents) ÷ hours, rounded half up to the cent: (cost × 100 / hoursCents).
        $line->hourly_cost = self::fromCents($hoursCents > 0 ? intdiv($cost * 100 * 2 + $hoursCents, $hoursCents * 2) : 0);
    }

    private function lockDraft(PayrollPeriod $period): PayrollPeriod
    {
        /** @var PayrollPeriod $locked */
        $locked = PayrollPeriod::query()->withoutGlobalScopes()->whereKey($period->id)->lockForUpdate()->firstOrFail();
        if ($locked->isClosed()) {
            throw new PayrollRuleViolation('Bảng lương tháng ' . $locked->period . ' đã chốt; mở lại trước khi sửa.', 'period');
        }

        return $locked;
    }

    private function requireManage(User $actor): void
    {
        if (!$this->canManage($actor)) {
            throw new PayrollRuleViolation('Bạn không có quyền quản lý bảng lương.', 'period');
        }
    }

    private function requireClose(User $actor): void
    {
        if (!$this->canClose($actor)) {
            throw new PayrollRuleViolation('Bạn không có quyền chốt / mở lại bảng lương.', 'period');
        }
    }

    private function requireTenant(User $actor, PayrollPeriod|PayrollLine $model): void
    {
        if ((string) $model->tenant_id !== (string) $actor->tenant_id) {
            throw new PayrollRuleViolation('Bảng lương không thuộc công ty.', 'period');
        }
    }

    private static function requireCalendar(int $workingDays, string $hoursPerDay): void
    {
        if ($workingDays < 1 || $workingDays > 31) {
            throw new PayrollRuleViolation('Số ngày công chuẩn phải từ 1 đến 31.', 'working_days');
        }
        if (!is_numeric($hoursPerDay) || (float) $hoursPerDay <= 0 || (float) $hoursPerDay > 24) {
            throw new PayrollRuleViolation('Số giờ mỗi ngày phải lớn hơn 0 và không quá 24.', 'hours_per_day');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function snapshot(PayrollLine $line): array
    {
        $row = [];
        foreach ([...PayrollLine::MONEY_COLUMNS, 'standard_hours', 'note'] as $column) {
            $value = $line->getAttribute($column);
            $row[$column] = $value === null ? null : (string) $value;
        }

        return $row;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(User $actor, string $action, string $entityType, string $entityId, ?array $before, array $after): void
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

    private static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    private static function decimal(string $value): string
    {
        return self::fromCents(self::toCents($value));
    }

    public static function toCents(string $value): int
    {
        $value = trim($value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-+');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) ($whole === '' ? '0' : $whole)) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }
}
