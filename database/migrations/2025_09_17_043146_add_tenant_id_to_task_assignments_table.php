<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->string('priority')->nullable()->after('assignment_type');
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('priority');
            $table->string('assigned_by')->nullable()->after('estimated_hours');
            $table->timestamp('due_date')->nullable()->after('assigned_by');
            
            // Add foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            
            // Add indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['priority']);
            $table->index(['due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('task_assignments')) {
            return;
        }

        if (!Schema::hasColumn('task_assignments', 'tenant_id')) {
            return;
        }

        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            try {
                Schema::table('task_assignments', function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
            } catch (\Throwable $e) {
                // Intentionally swallow for idempotent rollback in partial DB states.
            }

            try {
                Schema::table('task_assignments', function (Blueprint $table) {
                    $table->dropForeign(['assigned_by']);
                });
            } catch (\Throwable $e) {
                // Intentionally swallow for idempotent rollback in partial DB states.
            }
        }

        try {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'status']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->dropIndex(['priority']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->dropIndex(['due_date']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        foreach (['tenant_id', 'priority', 'estimated_hours', 'assigned_by', 'due_date'] as $column) {
            if (!Schema::hasColumn('task_assignments', $column)) {
                continue;
            }

            try {
                Schema::table('task_assignments', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            } catch (\Throwable $e) {
                // Intentionally swallow for idempotent rollback in partial DB states.
            }
        }
    }
};
