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
        Schema::create('dashboard_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('user_id');
            $table->string('tenant_id');
            $table->string('project_id')->nullable();
            $table->text('message');
            $table->string('type');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->boolean('is_read')->default(false);
            $table->timestamp('triggered_at')->nullable();
            $table->json('context')->nullable();
            $table->string('widget_id')->nullable();
            $table->string('metric_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id']);
            $table->index(['type']);
            $table->index(['severity']);
            $table->index(['triggered_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('widget_id')->references('id')->on('dashboard_widgets')->onDelete('set null');
            $table->foreign('metric_id')->references('id')->on('dashboard_metrics')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_alerts');
    }
};
