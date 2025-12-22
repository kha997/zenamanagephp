<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\DBDriver;

/**
 * Round 251: Update notifications table schema for Notifications Center Phase 1
 * 
 * Changes:
 * - Add module column (string 50)
 * - Update type to string(100)
 * - Rename body to message
 * - Add entity_type and entity_id columns
 * - Change read_at timestamp to is_read boolean
 * - Update indexes for new schema
 * 
 * SQLite compatible: Uses drop and recreate approach for schema changes
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite compatibility, we'll recreate the table
        // First, backup existing data if any
        $hasData = DB::table('notifications')->exists();
        $backupData = [];
        
        if ($hasData) {
            $backupData = DB::table('notifications')->get()->toArray();
        }
        
        // Drop and recreate table with new schema
        Schema::dropIfExists('notifications');
        
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('user_id');
            $table->string('module', 50)->nullable(); // tasks / documents / cost / rbac / system
            $table->string('type', 100); // e.g., task.assigned / co.needs_approval
            $table->string('title');
            $table->text('message')->nullable(); // Renamed from body
            $table->string('entity_type')->nullable(); // "task", "change_order", etc.
            $table->ulid('entity_id')->nullable();
            $table->boolean('is_read')->default(false); // Changed from read_at timestamp
            $table->timestamps();
            
            // For SQLite compatibility, use text() instead of json() for metadata
            if (DBDriver::isSqlite()) {
                $table->text('metadata')->nullable();
            } else {
                $table->json('metadata')->nullable();
            }
            
            // Foreign key constraints
            $table->foreign('tenant_id', 'notifications_tenant_id_foreign')
                  ->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id', 'notifications_user_id_foreign')
                  ->references('id')->on('users')->onDelete('cascade');
            
            // Indexes as per requirements
            $table->index(['tenant_id', 'user_id', 'is_read'], 'notifications_tenant_user_read_idx');
            $table->index(['created_at'], 'notifications_created_at_idx');
            $table->index(['module'], 'notifications_module_idx');
            $table->index(['entity_type', 'entity_id'], 'notifications_entity_idx');
        });
        
        // Restore data if any (migrate read_at to is_read)
        if ($hasData && !empty($backupData)) {
            foreach ($backupData as $row) {
                $isRead = !empty($row->read_at) ? true : false;
                $metadata = null;
                
                // Try to preserve metadata if it exists
                if (isset($row->metadata)) {
                    if (is_string($row->metadata)) {
                        $metadata = $row->metadata; // Already JSON string for SQLite
                    } else {
                        $metadata = json_encode($row->metadata);
                    }
                }
                
                // Map old fields to new schema
                DB::table('notifications')->insert([
                    'id' => $row->id,
                    'tenant_id' => $row->tenant_id,
                    'user_id' => $row->user_id,
                    'module' => null, // Will need to be set based on type
                    'type' => $row->type ?? 'system.notification',
                    'title' => $row->title,
                    'message' => $row->body ?? null,
                    'entity_type' => null,
                    'entity_id' => $row->project_id ?? null,
                    'is_read' => $isRead,
                    'metadata' => $metadata,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backup data
        $backupData = DB::table('notifications')->get()->toArray();
        
        // Drop and recreate with old schema
        Schema::dropIfExists('notifications');
        
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('tenant_id');
            $table->string('type');
            $table->enum('priority', ['critical', 'normal', 'low'])->default('normal');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link_url')->nullable();
            $table->enum('channel', ['inapp', 'email', 'webhook'])->default('inapp');
            $table->timestamp('read_at')->nullable();
            
            if (DBDriver::isSqlite()) {
                $table->text('data')->nullable();
                $table->text('metadata')->nullable();
            } else {
                $table->json('data')->nullable();
                $table->json('metadata')->nullable();
            }
            
            $table->string('event_key')->nullable();
            $table->ulid('project_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id', 'notifications_user_id_foreign')
                  ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tenant_id', 'notifications_tenant_id_foreign')
                  ->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('project_id', 'notifications_project_id_foreign')
                  ->references('id')->on('projects')->onDelete('cascade');

            // Old indexes
            $table->index(['user_id', 'read_at']);
            $table->index(['tenant_id']);
            $table->index(['priority']);
            $table->index(['channel']);
            $table->index(['project_id']);
            $table->index(['event_key']);
            $table->index(['type']);
        });
        
        // Restore data
        if (!empty($backupData)) {
            foreach ($backupData as $row) {
                $readAt = $row->is_read ? ($row->updated_at ?? now()) : null;
                
                DB::table('notifications')->insert([
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'tenant_id' => $row->tenant_id,
                    'type' => $row->type,
                    'priority' => 'normal',
                    'title' => $row->title,
                    'body' => $row->message ?? null,
                    'link_url' => null,
                    'channel' => 'inapp',
                    'read_at' => $readAt,
                    'data' => null,
                    'metadata' => $row->metadata ?? null,
                    'event_key' => null,
                    'project_id' => $row->entity_id ?? null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }
};
