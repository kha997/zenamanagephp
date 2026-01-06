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
        Schema::create('dashboard_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('metric_code')->unique();
            $table->string('code')->unique();
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->string('unit')->nullable();
            $table->text('description')->nullable();
            $table->json('calculation_config')->nullable();
            $table->json('display_config')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->ulid('tenant_id');
            $table->timestamps();

            $table->index(['category']);
            $table->index(['type']);
            $table->index(['tenant_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_metrics');
    }
};
