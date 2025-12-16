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
        Schema::create('template_apply_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('project_id');
            $table->string('tenant_id');
            $table->string('set_id');
            $table->string('preset_code')->nullable();
            $table->json('selections');
            $table->json('counts');
            $table->string('executor_id');
            $table->integer('duration_ms');
            $table->timestamp('created_at');

            // Foreign keys
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('set_id')->references('id')->on('template_sets')->onDelete('cascade');
            $table->foreign('executor_id')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('project_id');
            $table->index('tenant_id');
            $table->index('set_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_apply_logs');
    }
};

