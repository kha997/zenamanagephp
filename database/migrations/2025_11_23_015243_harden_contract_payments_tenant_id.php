<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Round 37: Payment Hardening - Make tenant_id NOT NULL
 * 
 * This migration:
 * 1. Repairs any existing NULL tenant_id by syncing from contracts
 * 2. Makes tenant_id NOT NULL to enforce invariant
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('contract_payments')) {
            return;
        }

        // Step 1: Repair existing NULL tenant_id by syncing from contracts
        // Update all contract_payments that have NULL tenant_id
        // Set tenant_id from the related contract
        // Use subquery approach for SQLite compatibility
        DB::statement("
            UPDATE contract_payments 
            SET tenant_id = (
                SELECT tenant_id 
                FROM contracts 
                WHERE contracts.id = contract_payments.contract_id 
                AND contracts.tenant_id IS NOT NULL
                LIMIT 1
            )
            WHERE tenant_id IS NULL
            AND contract_id IS NOT NULL
        ");

        // Step 2: Check if there are any remaining NULL values
        // If there are, log a warning but continue (they might be orphaned records)
        $nullCount = DB::table('contract_payments')->whereNull('tenant_id')->count();
        if ($nullCount > 0) {
            \Log::warning("contract_payments table has {$nullCount} records without tenant_id after repair attempt.");
        }

        // Step 3: Make tenant_id NOT NULL
        // Only proceed if all records have tenant_id
        if ($nullCount === 0) {
            Schema::table('contract_payments', function (Blueprint $table) {
                $table->string('tenant_id')->nullable(false)->change();
            });
        } else {
            \Log::warning("Skipping NOT NULL constraint for contract_payments.tenant_id due to {$nullCount} NULL values.");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('contract_payments')) {
            return;
        }

        // Revert tenant_id back to nullable
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->change();
        });
    }
};
