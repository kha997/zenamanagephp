<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-109 (Owner Gate 2 C-1, B-1, H-1, L-1, P-1): company staff register, one payroll sheet per month, one line per
 * person and month. New tables only; no existing table changes.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('staff_type', 20);
            $table->boolean('is_overhead')->default(false);
            $table->ulid('user_id')->nullable();
            $table->ulid('financial_party_id')->nullable();
            $table->decimal('standard_hours_override', 7, 2)->nullable();
            $table->decimal('annual_bonus_estimate', 15, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id', 'sm_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id', 'sm_user_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('financial_party_id', 'sm_party_id_fk')->references('id')->on('treasury_financial_parties')->nullOnDelete();
            $table->unique(['tenant_id', 'code'], 'sm_tenant_code_unique');
        });

        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->char('period', 7);
            $table->unsignedSmallInteger('working_days')->default(26);
            $table->decimal('hours_per_day', 4, 2)->default(8);
            $table->string('status', 20)->default('draft');
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'pp_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'period'], 'pp_tenant_period_unique');
        });

        Schema::create('payroll_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('payroll_period_id');
            $table->ulid('staff_member_id');
            foreach (['base_salary', 'allowance', 'overtime', 'bonus_paid', 'employer_insurance', 'other_company_cost',
                'employee_insurance', 'personal_income_tax', 'advance_deduction', 'other_deduction', 'bonus_accrual',
                'net_pay', 'company_cost'] as $column) {
                $table->decimal($column, 15, 2)->default(0);
            }
            $table->decimal('standard_hours', 7, 2)->default(0);
            $table->decimal('hourly_cost', 15, 2)->default(0);
            $table->date('paid_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'pl_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payroll_period_id', 'pl_period_id_fk')->references('id')->on('payroll_periods')->cascadeOnDelete();
            $table->foreign('staff_member_id', 'pl_staff_id_fk')->references('id')->on('staff_members');
            $table->unique(['payroll_period_id', 'staff_member_id'], 'pl_period_staff_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('staff_members');
    }
};
