<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Models\Notification;
use App\Models\Payroll\OverheadPeriod;
use App\Models\Payroll\PayrollPeriod;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GAP-120 (Gate 2 W-1, T-1, C-1) — month-end reminders written as in-app notifications (shown on the Today page).
 *
 * - time_review: submitted time (work date from the start of last month to today) waiting for review. Project entries
 *   go to the project PM; the PM's own entries and PM-less projects to payroll.close holders. Non-project entries go to
 *   payroll.manage holders other than their author. Only from three days before month end to day 5 of the next month.
 * - payroll_close / overhead_close: last month's payroll not closed, or closed with overhead still open. To
 *   payroll.close holders, every day from day 1 until closed, only for tenants with staff records (they use payroll).
 * - One notification per person, kind and month per day: event_key "<type>:<month>:<date>"; running twice a day is safe.
 */
class MonthEndReminderService
{
    public const TYPE_TIME_REVIEW = 'month_end.time_review';
    public const TYPE_PAYROLL_CLOSE = 'month_end.payroll_close';
    public const TYPE_OVERHEAD_CLOSE = 'month_end.overhead_close';

    /**
     * Read-only: what would be sent on that day.
     *
     * @return list<array{tenant_id: string, user_id: string, user_name: string, type: string, month: string, title: string, body: string, link_url: string}>
     */
    public function plan(Carbon $today): array
    {
        $today = $today->copy()->startOfDay();
        $reminders = [];
        if ($this->inReviewWindow($today)) {
            $reminders = array_merge($reminders, $this->timeReviewReminders($today));
        }
        $reminders = array_merge($reminders, $this->closeReminders($today));

        return $reminders;
    }

    /**
     * Write the planned reminders that were not already sent today. Returns how many were written.
     */
    public function send(Carbon $today): int
    {
        $written = 0;
        $date = $today->copy()->startOfDay()->toDateString();
        foreach ($this->plan($today) as $reminder) {
            $eventKey = $reminder['type'] . ':' . $reminder['month'] . ':' . $date;
            $exists = Notification::query()->withoutGlobalScopes()
                ->where('tenant_id', $reminder['tenant_id'])
                ->where('user_id', $reminder['user_id'])
                ->where('event_key', $eventKey)
                ->exists();
            if ($exists) {
                continue;
            }
            Notification::query()->withoutGlobalScopes()->create([
                'tenant_id' => $reminder['tenant_id'],
                'user_id' => $reminder['user_id'],
                'type' => $reminder['type'],
                'priority' => Notification::PRIORITY_NORMAL,
                'channel' => Notification::CHANNEL_INAPP,
                'title' => $reminder['title'],
                'body' => $reminder['body'],
                'link_url' => $reminder['link_url'],
                'event_key' => $eventKey,
                'data' => ['month' => $reminder['month']],
            ]);
            $written++;
        }

        return $written;
    }

    private function inReviewWindow(Carbon $today): bool
    {
        return $today->day >= $today->daysInMonth - 2 || $today->day <= 5;
    }

    /**
     * @return list<array{tenant_id: string, user_id: string, user_name: string, type: string, month: string, title: string, body: string, link_url: string}>
     */
    private function timeReviewReminders(Carbon $today): array
    {
        $rows = DB::table('time_entries')
            ->leftJoin('projects', 'projects.id', '=', 'time_entries.project_id')
            ->where('time_entries.status', TimeEntry::STATUS_SUBMITTED)
            ->whereDate('time_entries.work_date', '>=', $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString())
            ->whereDate('time_entries.work_date', '<=', $today->toDateString())
            ->select(['time_entries.tenant_id', 'time_entries.user_id', 'time_entries.project_id', 'time_entries.work_date', 'projects.pm_id', 'projects.tenant_id as project_tenant_id'])
            ->get();

        /** @var array<string, array<string, array{count: int, oldest: string}>> $byRecipient tenant => user => summary */
        $byRecipient = [];
        $holders = [];
        foreach ($rows as $row) {
            $tenantId = (string) $row->tenant_id;
            $holders[$tenantId] ??= [
                'close' => $this->holders($tenantId, 'payroll.close'),
                'manage' => $this->holders($tenantId, 'payroll.manage'),
            ];
            $author = (string) $row->user_id;
            if ($row->project_id !== null) {
                $pm = $row->pm_id !== null && (string) $row->project_tenant_id === $tenantId ? (string) $row->pm_id : null;
                $recipients = $pm !== null && $pm !== $author && $this->isActiveMember($tenantId, $pm) ? [$pm] : array_keys($holders[$tenantId]['close']);
            } else {
                $recipients = array_values(array_filter(array_keys($holders[$tenantId]['manage']), static fn (string $id): bool => $id !== $author));
                if ($recipients === []) {
                    $recipients = array_keys($holders[$tenantId]['close']);
                }
            }
            $date = substr((string) $row->work_date, 0, 10);
            foreach ($recipients as $recipient) {
                $current = $byRecipient[$tenantId][$recipient] ?? ['count' => 0, 'oldest' => $date];
                $byRecipient[$tenantId][$recipient] = ['count' => $current['count'] + 1, 'oldest' => min($current['oldest'], $date)];
            }
        }

        $reminders = [];
        foreach ($byRecipient as $tenantId => $users) {
            $names = User::query()->whereIn('id', array_keys($users))->pluck('name', 'id');
            foreach ($users as $userId => $summary) {
                $oldest = Carbon::parse($summary['oldest']);
                $reminders[] = [
                    'tenant_id' => (string) $tenantId,
                    'user_id' => (string) $userId,
                    'user_name' => (string) ($names[$userId] ?? $userId),
                    'type' => self::TYPE_TIME_REVIEW,
                    'month' => $today->format('Y-m'),
                    'title' => 'Nhắc duyệt giờ cuối tháng',
                    'body' => 'Còn ' . $summary['count'] . ' dòng giờ chờ bạn duyệt, cũ nhất ' . $oldest->format('d/m') . '. Cần duyệt trước khi chốt lương tháng.',
                    'link_url' => route('app.time.review', ['week' => $oldest->copy()->startOfWeek(Carbon::MONDAY)->toDateString()]),
                ];
            }
        }

        return $reminders;
    }

    /**
     * @return list<array{tenant_id: string, user_id: string, user_name: string, type: string, month: string, title: string, body: string, link_url: string}>
     */
    private function closeReminders(Carbon $today): array
    {
        $month = $today->copy()->subMonthNoOverflow()->format('Y-m');
        $label = $today->copy()->subMonthNoOverflow()->format('m/Y');
        $tenantIds = DB::table('staff_members')->distinct()->pluck('tenant_id')->map(static fn ($id): string => (string) $id);

        $reminders = [];
        foreach ($tenantIds as $tenantId) {
            /** @var PayrollPeriod|null $payroll */
            $payroll = PayrollPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('period', $month)->first();
            if ($payroll === null || $payroll->status !== PayrollPeriod::STATUS_CLOSED) {
                $type = self::TYPE_PAYROLL_CLOSE;
                $title = 'Chưa chốt lương tháng ' . $label;
                $body = 'Lương tháng ' . $label . ' chưa chốt. Duyệt hết giờ của tháng rồi chốt lương để tính chi phí nhân công.';
                $link = $payroll !== null ? route('operator.payroll.periods.show', $payroll->id) : route('operator.payroll.index');
            } else {
                $overhead = OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('period', $month)->first();
                if ($overhead !== null && $overhead->status === OverheadPeriod::STATUS_CLOSED) {
                    continue;
                }
                $type = self::TYPE_OVERHEAD_CLOSE;
                $title = 'Chưa chốt chi phí chung tháng ' . $label;
                $body = 'Lương tháng ' . $label . ' đã chốt nhưng chi phí chung chưa chốt; lời lỗ công trình vẫn là tạm tính.';
                $link = route('operator.payroll.overhead', ['month' => $month]);
            }
            foreach ($this->holders($tenantId, 'payroll.close') as $userId => $name) {
                $reminders[] = [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'user_name' => $name,
                    'type' => $type,
                    'month' => $month,
                    'title' => $title,
                    'body' => $body,
                    'link_url' => $link,
                ];
            }
        }

        return $reminders;
    }

    /**
     * Active users of the tenant holding the permission.
     *
     * @return array<string, string> user id => name
     */
    private function holders(string $tenantId, string $permission): array
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('roles.permissions', static fn ($q) => $q->where('name', $permission))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(static fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    private function isActiveMember(string $tenantId, string $userId): bool
    {
        return User::query()->whereKey($userId)->where('tenant_id', $tenantId)->where('is_active', true)->exists();
    }
}
