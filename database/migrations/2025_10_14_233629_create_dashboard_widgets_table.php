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
        if (!Schema::hasTable('dashboard_widgets')) {
            Schema::create('dashboard_widgets', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('type'); // chart, metric, table, text, etc.
                $table->string('category')->nullable(); // kpi, analytics, reports, etc.
                $table->text('description')->nullable();
                $table->json('config')->nullable(); // Widget configuration
                $table->json('permissions')->nullable(); // Required permissions
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['type', 'category']);
                $table->index(['is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};