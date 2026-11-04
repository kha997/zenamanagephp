<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP-109 C-1 / B-1 / H-1 — one person's payroll in one month: entered pay items, the 1/12 bonus accrual, and the
 * computed net pay, company cost, standard hours and hourly cost (frozen when the month is closed).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $payroll_period_id
 * @property string $staff_member_id
 * @property string $base_salary
 * @property string $allowance
 * @property string $overtime
 * @property string $bonus_paid
 * @property string $employer_insurance
 * @property string $other_company_cost
 * @property string $employee_insurance
 * @property string $personal_income_tax
 * @property string $advance_deduction
 * @property string $other_deduction
 * @property string $bonus_accrual
 * @property string $net_pay
 * @property string $company_cost
 * @property string $standard_hours
 * @property string $hourly_cost
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $payment_reference
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read StaffMember $staffMember
 */
class PayrollLine extends Model
{
    use HasUlids;
    use TenantScope;

    /** Entered by the accountant (C-1); bonus_accrual is prefilled from the annual estimate (B-1). */
    public const INPUT_COLUMNS = [
        'base_salary', 'allowance', 'overtime', 'bonus_paid', 'employer_insurance', 'other_company_cost',
        'employee_insurance', 'personal_income_tax', 'advance_deduction', 'other_deduction', 'bonus_accrual',
    ];

    public const MONEY_COLUMNS = [
        'base_salary', 'allowance', 'overtime', 'bonus_paid', 'employer_insurance', 'other_company_cost',
        'employee_insurance', 'personal_income_tax', 'advance_deduction', 'other_deduction', 'bonus_accrual',
        'net_pay', 'company_cost', 'hourly_cost',
    ];

    protected $table = 'payroll_lines';

    protected $fillable = [
        'tenant_id', 'payroll_period_id', 'staff_member_id',
        'base_salary', 'allowance', 'overtime', 'bonus_paid', 'employer_insurance', 'other_company_cost',
        'employee_insurance', 'personal_income_tax', 'advance_deduction', 'other_deduction', 'bonus_accrual',
        'net_pay', 'company_cost', 'standard_hours', 'hourly_cost', 'paid_at', 'payment_reference', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'base_salary' => 'decimal:2', 'allowance' => 'decimal:2', 'overtime' => 'decimal:2', 'bonus_paid' => 'decimal:2',
        'employer_insurance' => 'decimal:2', 'other_company_cost' => 'decimal:2', 'employee_insurance' => 'decimal:2',
        'personal_income_tax' => 'decimal:2', 'advance_deduction' => 'decimal:2', 'other_deduction' => 'decimal:2',
        'bonus_accrual' => 'decimal:2', 'net_pay' => 'decimal:2', 'company_cost' => 'decimal:2',
        'standard_hours' => 'decimal:2', 'hourly_cost' => 'decimal:2', 'paid_at' => 'date',
    ];

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_member_id');
    }

    /** @return BelongsTo<PayrollPeriod, $this> */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
