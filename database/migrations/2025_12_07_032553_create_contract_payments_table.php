<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * Creates contract_payments table for actual payments (tiền thực chi).
 * Note: A contract_payments table may exist from Round 36 (payment schedules).
 * This migration creates the Round 221 structure. If conflicts occur, 
 * the old table may need to be renamed or migrated.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Round 221: Create contract_payments table for actual payments
        // Note: If a contract_payments table exists from Round 36 (payment schedules),
        // you may need to rename it first or adjust this migration
        if (!Schema::hasTable('contract_payments') || 
            (Schema::hasTable('contract_payments') && !Schema::hasColumn('contract_payments', 'paid_date'))) {
            // Check if we need to create new table or add columns
            $needsNewTable = !Schema::hasTable('contract_payments');
            
            if ($needsNewTable) {
                Schema::create('contract_payments', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('project_id');
                $table->string('contract_id');
                $table->string('certificate_id')->nullable(); // Optional link to contract_payment_certificates
                $table->date('paid_date'); // Actual pay date
                $table->decimal('amount_paid', 18, 2); // Số tiền thực trả
                $table->string('currency')->nullable(); // Default same as contract; keep flexible
                $table->string('payment_method')->nullable(); // e.g. 'bank_transfer', 'cash', 'offset'
                $table->string('reference_no')->nullable(); // Bank ref / internal ref
                $table->json('metadata')->nullable();
                $table->ulid('created_by')->nullable();
                $table->ulid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
                $table->foreign('certificate_id')->references('id')->on('contract_payment_certificates')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index(['tenant_id', 'project_id']);
                $table->index(['tenant_id', 'contract_id']);
                $table->index(['tenant_id', 'contract_id', 'certificate_id']);
                });
            } else {
                // Table exists but missing Round 221 columns - add them
                // Also make Round 36 required columns nullable to support Round 221 usage
                Schema::table('contract_payments', function (Blueprint $table) {
                    // Make Round 36 required columns nullable to support Round 221
                    if (Schema::hasColumn('contract_payments', 'name')) {
                        $table->string('name')->nullable()->change();
                    }
                    if (Schema::hasColumn('contract_payments', 'due_date')) {
                        $table->date('due_date')->nullable()->change();
                    }
                    if (Schema::hasColumn('contract_payments', 'amount')) {
                        $table->decimal('amount', 15, 2)->nullable()->change();
                    }
                    if (Schema::hasColumn('contract_payments', 'currency')) {
                        $table->string('currency', 3)->nullable()->change();
                    }
                    
                    // Add Round 221 columns
                    if (!Schema::hasColumn('contract_payments', 'project_id')) {
                        $table->string('project_id')->nullable()->after('tenant_id');
                    }
                    if (!Schema::hasColumn('contract_payments', 'certificate_id')) {
                        $table->string('certificate_id')->nullable()->after('contract_id');
                    }
                    if (!Schema::hasColumn('contract_payments', 'paid_date')) {
                        $table->date('paid_date')->nullable()->after('certificate_id');
                    }
                    if (!Schema::hasColumn('contract_payments', 'amount_paid')) {
                        $table->decimal('amount_paid', 18, 2)->nullable()->after('paid_date');
                    }
                    if (!Schema::hasColumn('contract_payments', 'payment_method')) {
                        $table->string('payment_method')->nullable()->after('currency');
                    }
                    if (!Schema::hasColumn('contract_payments', 'reference_no')) {
                        $table->string('reference_no')->nullable()->after('payment_method');
                    }
                    if (!Schema::hasColumn('contract_payments', 'metadata')) {
                        $table->json('metadata')->nullable();
                    }
                    if (!Schema::hasColumn('contract_payments', 'created_by')) {
                        $table->ulid('created_by')->nullable();
                    }
                    if (!Schema::hasColumn('contract_payments', 'updated_by')) {
                        $table->ulid('updated_by')->nullable();
                    }
                });
                
                // Add foreign keys if they don't exist
                Schema::table('contract_payments', function (Blueprint $table) {
                    if (Schema::hasColumn('contract_payments', 'project_id') && 
                        !$this->foreignKeyExists('contract_payments', 'contract_payments_project_id_foreign')) {
                        $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                    }
                    if (Schema::hasColumn('contract_payments', 'certificate_id') && 
                        !$this->foreignKeyExists('contract_payments', 'contract_payments_certificate_id_foreign')) {
                        $table->foreign('certificate_id')->references('id')->on('contract_payment_certificates')->onDelete('set null');
                    }
                    if (Schema::hasColumn('contract_payments', 'created_by') && 
                        !$this->foreignKeyExists('contract_payments', 'contract_payments_created_by_foreign')) {
                        $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('contract_payments', 'updated_by') && 
                        !$this->foreignKeyExists('contract_payments', 'contract_payments_updated_by_foreign')) {
                        $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                    }
                });
            }
        }
    }

    /**
     * Check if a foreign key exists
     */
    private function foreignKeyExists(string $table, string $keyName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        if ($connection->getDriverName() === 'sqlite') {
            // SQLite doesn't support foreign key constraints in the same way
            return false;
        }
        
        $result = $connection->select(
            "SELECT CONSTRAINT_NAME 
             FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? 
             AND TABLE_NAME = ? 
             AND CONSTRAINT_NAME = ?",
            [$database, $table, $keyName]
        );
        
        return count($result) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop the table - it may have been created in Round 36
        // Only remove Round 221 specific columns if needed
        if (Schema::hasTable('contract_payments')) {
            Schema::table('contract_payments', function (Blueprint $table) {
                $columnsToDrop = ['certificate_id', 'paid_date', 'amount_paid', 'payment_method', 'reference_no'];
                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('contract_payments', $column)) {
                        if ($column === 'certificate_id') {
                            $table->dropForeign(['certificate_id']);
                        }
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
