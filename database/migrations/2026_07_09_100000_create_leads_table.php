<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CRM slice 1 — domain model port từ spec kha997/crm-zena (Prisma → Laravel):
// leads = LeadInbox (capture nhanh), accounts = Account, opportunities = Opportunity.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('account_code')->nullable()->unique();
            $table->string('account_type')->default('individual'); // individual|company
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('tax_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('province_or_city')->nullable();
            $table->string('source_summary')->nullable();
            $table->ulid('owner_id')->nullable();
            $table->string('status')->default('active'); // active|inactive|archived
            $table->timestamps();

            $table->foreign('tenant_id', 'accounts_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('owner_id', 'accounts_owner_id_foreign')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status'], 'accounts_tenant_status_index');
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('account_id');
            $table->string('opportunity_name');
            $table->string('service_category')->default('architecture');
            // architecture|interior|landscape|structure|mep|construction|inspection|consulting|combined_package
            $table->text('service_scope_summary')->nullable();
            // Pipeline 14 stage theo spec crm-zena
            $table->string('pipeline_stage')->default('new_lead');
            $table->string('forecast_category')->default('pipeline');
            // pipeline|best_case|commit|closed_won|closed_lost|nurture
            $table->decimal('estimated_fee', 18, 0)->nullable();
            $table->decimal('estimated_project_value', 18, 0)->nullable();
            $table->unsignedTinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->ulid('sales_owner_id')->nullable();
            $table->ulid('technical_owner_id')->nullable();
            $table->string('priority')->default('medium'); // low|medium|high
            $table->string('lost_reason')->nullable();
            $table->ulid('converted_project_id')->nullable();
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'opportunities_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('account_id', 'opportunities_account_id_foreign')
                ->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('sales_owner_id', 'opportunities_sales_owner_foreign')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('technical_owner_id', 'opportunities_technical_owner_foreign')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('converted_project_id', 'opportunities_converted_project_foreign')
                ->references('id')->on('projects')->nullOnDelete();
            $table->foreign('created_by', 'opportunities_created_by_foreign')
                ->references('id')->on('users');

            $table->index(['tenant_id', 'pipeline_stage'], 'opportunities_tenant_stage_index');
            $table->index(['account_id'], 'opportunities_account_index');
            $table->index(['expected_close_date'], 'opportunities_close_date_index');
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('contact_hint'); // tên/SĐT/Zalo thô lúc capture
            $table->text('project_description')->nullable();
            $table->string('source')->default('other');
            // facebook|zalo|referral|website|walk_in|hotline|other
            $table->string('status')->default('new'); // new|converted|discarded
            $table->ulid('converted_opportunity_id')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->ulid('captured_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'leads_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('converted_opportunity_id', 'leads_converted_opportunity_foreign')
                ->references('id')->on('opportunities')->nullOnDelete();
            $table->foreign('captured_by', 'leads_captured_by_foreign')
                ->references('id')->on('users');

            $table->index(['tenant_id', 'status'], 'leads_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('accounts');
    }
};
