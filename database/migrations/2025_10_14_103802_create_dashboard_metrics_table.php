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
        if (!Schema::hasTable('dashboard_metrics')) {
            Schema::create('dashboard_metrics', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('unit')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('category')->nullable();
                $table->json('config')->nullable();
                $table->ulid('project_id')->nullable();
                $table->ulid('tenant_id')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'category']);
                $table->index(['project_id', 'tenant_id']);
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
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