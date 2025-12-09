<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 219: Core Contracts & Budget (Backend-first)
     * Adds missing fields to contracts table for Round 219 requirements
     */
    public function up(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                // Add type field if not exists
                if (!Schema::hasColumn('contracts', 'type')) {
                    $table->string('type')->nullable()->after('code'); // 'main', 'subcontract', 'supply', 'consultant'
                }
                
                // Add party_name if not exists (rename from client_name if needed, or add as new)
                if (!Schema::hasColumn('contracts', 'party_name')) {
                    $table->string('party_name')->nullable()->after('name');
                }
                
                // Add base_amount if not exists (rename from total_value if needed, or add as new)
                if (!Schema::hasColumn('contracts', 'base_amount')) {
                    $table->decimal('base_amount', 18, 2)->nullable()->after('currency');
                }
                
                // Add vat_percent if not exists
                if (!Schema::hasColumn('contracts', 'vat_percent')) {
                    $table->decimal('vat_percent', 5, 2)->nullable()->after('base_amount');
                }
                
                // Add total_amount_with_vat if not exists
                if (!Schema::hasColumn('contracts', 'total_amount_with_vat')) {
                    $table->decimal('total_amount_with_vat', 18, 2)->nullable()->after('vat_percent');
                }
                
                // Add retention_percent if not exists
                if (!Schema::hasColumn('contracts', 'retention_percent')) {
                    $table->decimal('retention_percent', 5, 2)->nullable()->after('total_amount_with_vat');
                }
                
                // Add metadata if not exists
                if (!Schema::hasColumn('contracts', 'metadata')) {
                    $table->json('metadata')->nullable()->after('notes');
                }
                
                // Rename created_by_id to created_by if needed (or keep both)
                // Rename updated_by_id to updated_by if needed (or keep both)
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (Schema::hasColumn('contracts', 'type')) {
                    $table->dropColumn('type');
                }
                if (Schema::hasColumn('contracts', 'party_name')) {
                    $table->dropColumn('party_name');
                }
                if (Schema::hasColumn('contracts', 'base_amount')) {
                    $table->dropColumn('base_amount');
                }
                if (Schema::hasColumn('contracts', 'vat_percent')) {
                    $table->dropColumn('vat_percent');
                }
                if (Schema::hasColumn('contracts', 'total_amount_with_vat')) {
                    $table->dropColumn('total_amount_with_vat');
                }
                if (Schema::hasColumn('contracts', 'retention_percent')) {
                    $table->dropColumn('retention_percent');
                }
                if (Schema::hasColumn('contracts', 'metadata')) {
                    $table->dropColumn('metadata');
                }
            });
        }
    }
};
