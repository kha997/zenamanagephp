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
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('order');
            $table->string('created_by')->nullable()->after('metadata');
            
            // Add foreign key for created_by
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('project_milestones')) {
            return;
        }

        try {
            Schema::table('project_milestones', function (Blueprint $table): void {
                $table->dropForeign(['created_by']);
            });
        } catch (\Throwable) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        $existingColumns = [];
        foreach (['metadata', 'created_by'] as $column) {
            if (Schema::hasColumn('project_milestones', $column)) {
                $existingColumns[] = $column;
            }
        }

        if ($existingColumns === []) {
            return;
        }

        try {
            Schema::table('project_milestones', function (Blueprint $table) use ($existingColumns): void {
                $table->dropColumn($existingColumns);
            });
        } catch (\Throwable) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }
};
