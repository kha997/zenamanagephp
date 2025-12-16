<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\DBDriver;

/**
 * Round 235: Audit Log Framework
 * 
 * Creates audit_logs table for system-wide audit logging
 * 
 * Round 250: Fixed SQLite compatibility - use text() for SQLite instead of json()
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Round 250: For SQLite compatibility, always drop and recreate if table exists with wrong schema
        // In test environments with RefreshDatabase, this should be a fresh table anyway
        if (Schema::hasTable('audit_logs')) {
            // Check if table has the new structure (payload_before/payload_after columns)
            $hasNewStructure = Schema::hasColumn('audit_logs', 'payload_before') 
                && Schema::hasColumn('audit_logs', 'payload_after');
            
            if (!$hasNewStructure) {
                // Old table structure exists, drop it and recreate
                Schema::dropIfExists('audit_logs');
            } else {
                // Table exists with correct structure, just ensure columns are correct type
                // For SQLite, we need to check if columns are text() not json()
                // But SQLite doesn't easily allow column type changes, so we'll rely on
                // the migration running fresh in test environments
                return;
            }
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable(); // Nullable for system-wide actions
            $table->ulid('user_id')->nullable(); // Nullable for system actions
            $table->string('action'); // e.g., 'role.created', 'co.approved', 'payment.marked_paid'
            $table->string('entity_type')->nullable(); // e.g., 'Role', 'User', 'Contract', 'ChangeOrder'
            $table->string('entity_id')->nullable(); // ULID of the entity
            $table->string('project_id')->nullable(); // ULID for project-related actions
            // Use text() for SQLite compatibility, json() for MySQL/PostgreSQL
            // Laravel's array cast will handle JSON encoding/decoding automatically
            // Check driver at runtime inside the closure
            $isSqlite = DBDriver::isSqlite();
            if ($isSqlite) {
                $table->text('payload_before')->nullable(); // State before change
                $table->text('payload_after')->nullable(); // State after change
            } else {
                $table->json('payload_before')->nullable(); // State before change
                $table->json('payload_after')->nullable(); // State after change
            }
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            // Indexes for filtering
            $table->index(['tenant_id', 'entity_type', 'entity_id', 'created_at']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
            $table->index(['tenant_id', 'action', 'created_at']);
            $table->index(['tenant_id', 'project_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
