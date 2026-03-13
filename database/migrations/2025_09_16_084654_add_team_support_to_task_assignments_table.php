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
            // Add team support columns
            $table->ulid('team_id')->nullable()->after('user_id');
            $table->string('assignment_type')->default('user')->after('team_id'); // user, team
            
            // Add foreign key for team
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            
            // Add indexes
            $table->index(['team_id', 'assignment_type']);
            $table->index(['assignment_type']);
            
            // Add constraint to ensure either user_id or team_id is set, but not both
            // Note: MySQL doesn't support CHECK constraints, so we'll handle this in application logic
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

        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            try {
                Schema::table('task_assignments', function (Blueprint $table): void {
                    $table->dropForeign(['team_id']);
                });
            } catch (\Throwable) {
                // Intentionally swallow for idempotent rollback in partial DB states.
            }
        }

        try {
            Schema::table('task_assignments', function (Blueprint $table): void {
                $table->dropIndex(['team_id', 'assignment_type']);
            });
        } catch (\Throwable) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('task_assignments', function (Blueprint $table): void {
                $table->dropIndex(['assignment_type']);
            });
        } catch (\Throwable) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        foreach (['team_id', 'assignment_type'] as $column) {
            if (!Schema::hasColumn('task_assignments', $column)) {
                continue;
            }

            try {
                Schema::table('task_assignments', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            } catch (\Throwable) {
                // Intentionally swallow for idempotent rollback in partial DB states.
            }
        }
    }
};
