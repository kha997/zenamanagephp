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
        Schema::create('task_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('template_id')->index();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('order_index')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for efficient queries
            $table->index(['tenant_id', 'template_id']);
            $table->index(['tenant_id', 'template_id', 'order_index']);
            
            // Foreign key constraint (optional, but recommended)
            $table->foreign('template_id')->references('id')->on('templates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
