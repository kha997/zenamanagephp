<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * GAP-109 (Gate 2 Q-1) — the company staff register. Only payroll.manage holders write it; links to a login or a
 * Treasury party must stay inside the tenant; every create / update is audited with old and new values.
 */
class StaffService
{
    private const AUDITED = [
        'code', 'name', 'staff_type', 'is_overhead', 'user_id', 'financial_party_id', 'standard_hours_override',
        'annual_bonus_estimate', 'start_date', 'end_date', 'is_active',
    ];

    public function canManage(User $actor): bool
    {
        return $actor->hasPermission('payroll.manage');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(User $actor, array $data): StaffMember
    {
        $attributes = $this->validated($actor, $data, null);

        return DB::transaction(function () use ($actor, $attributes): StaffMember {
            $staff = StaffMember::query()->create($attributes + ['tenant_id' => (string) $actor->tenant_id]);
            $this->audit($actor, $staff, 'payroll.staff.created', null, $this->snapshot($staff));

            return $staff;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $actor, StaffMember $staff, array $data): StaffMember
    {
        $this->requireSameTenant($actor, $staff);
        $attributes = $this->validated($actor, $data, $staff);

        return DB::transaction(function () use ($actor, $staff, $attributes): StaffMember {
            /** @var StaffMember $locked */
            $locked = StaffMember::query()->withoutGlobalScopes()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            $locked->fill($attributes);
            $locked->save();
            $this->audit($actor, $locked, 'payroll.staff.updated', $before, $this->snapshot($locked));

            return $locked;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(User $actor, array $data, ?StaffMember $current): array
    {
        if (!$this->canManage($actor)) {
            throw new PayrollRuleViolation('Bạn không có quyền quản lý nhân sự.', 'code');
        }
        $tenantId = (string) $actor->tenant_id;
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new PayrollRuleViolation('Mã và tên nhân sự là bắt buộc.', $code === '' ? 'code' : 'name');
        }
        $type = (string) ($data['staff_type'] ?? '');
        if (!array_key_exists($type, StaffMember::TYPES)) {
            throw new PayrollRuleViolation('Loại nhân sự không hợp lệ.', 'staff_type');
        }
        $duplicate = StaffMember::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->when($current !== null, static fn ($q) => $q->whereKeyNot($current?->id))
            ->exists();
        if ($duplicate) {
            throw new PayrollRuleViolation('Mã nhân sự đã có trong công ty.', 'code');
        }

        $userId = self::nullable($data['user_id'] ?? null);
        if ($userId !== null && !User::query()->whereKey($userId)->where('tenant_id', $tenantId)->exists()) {
            throw new PayrollRuleViolation('Tài khoản đăng nhập không thuộc công ty.', 'user_id');
        }
        $partyId = self::nullable($data['financial_party_id'] ?? null);
        if ($partyId !== null && !DB::table('treasury_financial_parties')->where('id', $partyId)->where('tenant_id', $tenantId)->exists()) {
            throw new PayrollRuleViolation('Đối tác tài chính không thuộc công ty.', 'financial_party_id');
        }

        $start = self::date($data['start_date'] ?? null, 'start_date', true);
        $end = self::date($data['end_date'] ?? null, 'end_date', false);
        if ($end !== null && $end < $start) {
            throw new PayrollRuleViolation('Ngày nghỉ phải từ ngày bắt đầu trở đi.', 'end_date');
        }

        $hours = self::nullable($data['standard_hours_override'] ?? null);
        if ($hours !== null && (!is_numeric($hours) || (float) $hours <= 0 || (float) $hours > 744)) {
            throw new PayrollRuleViolation('Giờ chuẩn riêng phải lớn hơn 0 và không quá 744 giờ / tháng.', 'standard_hours_override');
        }
        $bonus = self::nullable($data['annual_bonus_estimate'] ?? null) ?? '0';
        if (!is_numeric($bonus) || (float) $bonus < 0) {
            throw new PayrollRuleViolation('Thưởng dự kiến năm không được âm.', 'annual_bonus_estimate');
        }

        return [
            'code' => mb_substr($code, 0, 50),
            'name' => mb_substr($name, 0, 255),
            'staff_type' => $type,
            'is_overhead' => filter_var($data['is_overhead'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'user_id' => $userId,
            'financial_party_id' => $partyId,
            'standard_hours_override' => $hours === null ? null : number_format((float) $hours, 2, '.', ''),
            'annual_bonus_estimate' => number_format((float) $bonus, 2, '.', ''),
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => $end === null || $end >= now()->toDateString(),
        ];
    }

    private function requireSameTenant(User $actor, StaffMember $staff): void
    {
        if ((string) $staff->tenant_id !== (string) $actor->tenant_id) {
            throw new PayrollRuleViolation('Nhân sự không thuộc công ty.', 'code');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(StaffMember $staff): array
    {
        $row = [];
        foreach (self::AUDITED as $column) {
            $value = $staff->getAttribute($column);
            $row[$column] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $row;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(User $actor, StaffMember $staff, string $action, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $staff->tenant_id,
            'action' => $action,
            'entity_type' => 'staff_member',
            'entity_id' => (string) $staff->id,
            'old_data' => $before,
            'new_data' => $after,
        ]);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function date(mixed $value, string $field, bool $required): ?string
    {
        $value = self::nullable($value);
        if ($value === null) {
            if ($required) {
                throw new PayrollRuleViolation('Ngày bắt đầu là bắt buộc.', $field);
            }

            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new PayrollRuleViolation('Ngày không hợp lệ.', $field);
        }

        return $value;
    }
}
