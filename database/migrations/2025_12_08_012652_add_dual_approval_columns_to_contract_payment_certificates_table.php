<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 241: Cost Dual-Approval Workflow (Phase 2)
 * 
 * Add dual approval columns to contract_payment_certificates table
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contract_payment_certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('contract_payment_certificates', 'first_approved_by')) {
                $table->ulid('first_approved_by')->nullable()->after('updated_by');
            }
            if (!Schema::hasColumn('contract_payment_certificates', 'first_approved_at')) {
                $table->timestamp('first_approved_at')->nullable()->after('first_approved_by');
            }
            if (!Schema::hasColumn('contract_payment_certificates', 'second_approved_by')) {
                $table->ulid('second_approved_by')->nullable()->after('first_approved_at');
            }
            if (!Schema::hasColumn('contract_payment_certificates', 'second_approved_at')) {
                $table->timestamp('second_approved_at')->nullable()->after('second_approved_by');
            }
            if (!Schema::hasColumn('contract_payment_certificates', 'requires_dual_approval')) {
                $table->boolean('requires_dual_approval')->default(false)->after('second_approved_at');
            }
        });

        // Add foreign keys and indexes
        Schema::table('contract_payment_certificates', function (Blueprint $table) {
            if (Schema::hasColumn('contract_payment_certificates', 'first_approved_by')) {
                $table->foreign('first_approved_by')->references('id')->on('users')->onDelete('set null');
                $table->index('first_approved_by');
            }
            if (Schema::hasColumn('contract_payment_certificates', 'second_approved_by')) {
                $table->foreign('second_approved_by')->references('id')->on('users')->onDelete('set null');
                $table->index('second_approved_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_payment_certificates', function (Blueprint $table) {
            if (Schema::hasColumn('contract_payment_certificates', 'second_approved_by')) {
                $table->dropForeign(['second_approved_by']);
                $table->dropIndex(['second_approved_by']);
            }
            if (Schema::hasColumn('contract_payment_certificates', 'first_approved_by')) {
                $table->dropForeign(['first_approved_by']);
                $table->dropIndex(['first_approved_by']);
            }
            $table->dropColumn([
                'requires_dual_approval',
                'second_approved_at',
                'second_approved_by',
                'first_approved_at',
                'first_approved_by',
            ]);
        });
    }
};
