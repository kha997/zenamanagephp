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
        if (!Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->ulid('team_lead_id')->nullable();
                $table->string('department')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->ulid('created_by')->nullable();
                $table->ulid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('team_lead_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index(['tenant_id', 'is_active']);
                $table->index(['team_lead_id']);
                $table->index(['department']);
                $table->index(['created_by']);
                
                // Unique constraint
                $table->unique(['tenant_id', 'name'], 'unique_team_name_per_tenant');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};