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
        Schema::create('template_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false);
            $table->string('created_by');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index('is_active');
            $table->index('version');
            $table->index('tenant_id');
            
            // Unique constraint: (tenant_id, code) for tenant-specific templates
            // Note: For global templates (tenant_id = NULL), uniqueness on code alone will be enforced
            // at the application level in TemplateSet model/service since MySQL doesn't support
            // partial unique indexes. The composite unique will allow multiple NULL tenant_id values
            // with the same code, so we need application-level validation.
            $table->unique(['tenant_id', 'code'], 'unique_tenant_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_sets');
    }
};

