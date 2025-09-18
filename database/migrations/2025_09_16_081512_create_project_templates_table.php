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
        if (!Schema::hasTable('project_templates')) {
            Schema::create('project_templates', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category');
                $table->json('template_data'); // Project data template
                $table->json('milestones')->nullable(); // Milestones template
                $table->boolean('is_public')->default(false);
                $table->string('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                
                // Indexes
                $table->index(['tenant_id', 'category']);
                $table->index(['tenant_id', 'is_public']);
                $table->index('category');
                $table->index('created_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_templates');
    }
};