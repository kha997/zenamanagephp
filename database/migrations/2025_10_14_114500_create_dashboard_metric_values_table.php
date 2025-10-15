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
        if (!Schema::hasTable('dashboard_metric_values')) {
            Schema::create('dashboard_metric_values', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('metric_id');
                $table->ulid('project_id')->nullable();
                $table->ulid('tenant_id');
                $table->decimal('value', 15, 4);
                $table->json('metadata')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->foreign('metric_id')->references('id')->on('dashboard_metrics')->onDelete('cascade');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                
                $table->index(['metric_id', 'recorded_at']);
                $table->index(['project_id', 'tenant_id', 'recorded_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_metric_values');
    }
};