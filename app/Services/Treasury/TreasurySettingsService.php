<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * GAP-108 (Gate 2 N-1, S-1) — tenant-wide Treasury thresholds kept in tenants.settings (as the GAP-106 evidence
 * switch): days before unreconciled funding alerts and the term after which an advance without a due date is overdue.
 * Only owner-level users change them; every change is audited. Missing or invalid values fall back to the S6 defaults.
 */
class TreasurySettingsService
{
    public const FUNDING_DAYS_KEY = 'treasury_unreconciled_funding_days';

    public const ADVANCE_TERM_KEY = 'treasury_advance_default_term_days';

    public const DEFAULT_FUNDING_DAYS = 30;

    public const DEFAULT_ADVANCE_TERM_DAYS = 90;

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 365;

    /**
     * @return array{unreconciled_funding_days: int, advance_default_term_days: int}
     */
    public function thresholds(string $tenantId): array
    {
        $settings = Tenant::query()->whereKey($tenantId)->value('settings');
        $settings = is_string($settings) ? (array) json_decode($settings, true) : (array) $settings;

        return [
            'unreconciled_funding_days' => self::valid($settings[self::FUNDING_DAYS_KEY] ?? null) ?? self::DEFAULT_FUNDING_DAYS,
            'advance_default_term_days' => self::valid($settings[self::ADVANCE_TERM_KEY] ?? null) ?? self::DEFAULT_ADVANCE_TERM_DAYS,
        ];
    }

    public function canChange(User $actor): bool
    {
        return $actor->hasPermission('treasury.self_approve_expense');
    }

    public function setThresholds(User $actor, int $fundingDays, int $advanceTermDays): void
    {
        if (!$this->canChange($actor)) {
            throw new TreasuryRuleViolation('Chỉ chủ doanh nghiệp đổi được ngưỡng cảnh báo.', 'unreconciled_funding_days');
        }
        foreach (['unreconciled_funding_days' => $fundingDays, 'advance_default_term_days' => $advanceTermDays] as $field => $value) {
            if (self::valid($value) === null) {
                throw new TreasuryRuleViolation(sprintf('Số ngày phải từ %d đến %d.', self::MIN_DAYS, self::MAX_DAYS), $field);
            }
        }

        $tenantId = (string) $actor->tenant_id;
        DB::transaction(function () use ($actor, $tenantId, $fundingDays, $advanceTermDays): void {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $settings = (array) ($tenant->settings ?? []);
            $before = $this->thresholds($tenantId);
            $settings[self::FUNDING_DAYS_KEY] = $fundingDays;
            $settings[self::ADVANCE_TERM_KEY] = $advanceTermDays;
            $tenant->settings = $settings;
            $tenant->save();
            AuditLog::query()->create([
                'user_id' => (string) $actor->id,
                'tenant_id' => (string) $tenant->id,
                'action' => 'treasury.thresholds.changed',
                'entity_type' => 'tenant',
                'entity_id' => (string) $tenant->id,
                'old_data' => $before,
                'new_data' => ['unreconciled_funding_days' => $fundingDays, 'advance_default_term_days' => $advanceTermDays],
            ]);
        });
    }

    private static function valid(mixed $value): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }
        $days = (int) $value;

        return $days >= self::MIN_DAYS && $days <= self::MAX_DAYS ? $days : null;
    }
}
