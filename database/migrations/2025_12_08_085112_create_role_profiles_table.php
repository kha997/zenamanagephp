<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 244: Role Access Profiles
 * 
 * Creates role_profiles table for managing role templates/presets
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('roles'); // Array of role IDs or slugs
            $table->boolean('is_active')->default(true);
            $table->string('tenant_id'); // ULID - tenant isolation
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Unique constraint: (tenant_id, name)
            $table->unique(['tenant_id', 'name']);

            // Indexes for performance
            $table->index(['tenant_id', 'is_active']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_profiles');
    }
};
