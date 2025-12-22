<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\SqliteCompatibleMigration;
use App\Support\DBDriver;

return new class extends Migration
{
    use SqliteCompatibleMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('dashboard_metrics')) {
            Schema::create('dashboard_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('metric_type'); // 'project_count', 'task_count', 'budget_variance', etc.
            $table->string('metric_name');
            $table->decimal('value', 15, 2)->nullable();
            $table->string('unit')->nullable(); // 'count', 'percentage', 'currency', etc.
            $table->json('metadata')->nullable(); // Additional data for the metric
            $table->timestamp('calculated_at');
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'metric_type']);
            $table->index(['user_id', 'metric_type']);
            $table->index('calculated_at');

            // Add foreign key constraints if supported
            $this->addForeignKeyConstraint($table, 'tenant_id', 'id', 'tenants');
            $this->addForeignKeyConstraint($table, 'user_id', 'id', 'users');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_metrics');
    }
};