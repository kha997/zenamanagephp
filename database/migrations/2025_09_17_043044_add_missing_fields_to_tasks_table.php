<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\DBDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add missing fields that are in the model but not in migration
            $table->boolean('is_hidden')->default(false)->after('priority');
            $table->string('visibility')->default('team')->after('is_hidden');
            $table->boolean('client_approved')->default(false)->after('visibility');
            $table->string('component_id')->nullable()->after('project_id');
            $table->string('phase_id')->nullable()->after('component_id');
            $table->string('title')->nullable()->after('name');
            $table->string('assigned_to')->nullable()->after('assignee_id');
            $table->decimal('spent_hours', 8, 2)->nullable()->after('actual_hours');
            $table->string('parent_id')->nullable()->after('spent_hours');
            $table->integer('order')->default(0)->after('parent_id');
            $table->string('conditional_tag')->nullable()->after('dependencies');
            $table->string('created_by')->nullable()->after('conditional_tag');
            $table->string('updated_by')->nullable()->after('created_by');
            $table->json('watchers')->nullable()->after('updated_by');
            
            // Add foreign key constraints
            $table->foreign('component_id')->references('id')->on('zena_components')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Add indexes
            $table->index(['component_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['parent_id']);
            $table->index(['is_hidden']);
            $table->index(['visibility']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Drop foreign keys first
            if (DBDriver::isMysql()) {
                $table->dropForeign(['component_id']);
            }
            if (DBDriver::isMysql()) {
                $table->dropForeign(['assigned_to']);
            }
            if (DBDriver::isMysql()) {
                $table->dropForeign(['parent_id']);
            }
            if (DBDriver::isMysql()) {
                $table->dropForeign(['created_by']);
            }
            if (DBDriver::isMysql()) {
                $table->dropForeign(['updated_by']);
            }
            
            // Drop indexes
            $table->dropIndex(['component_id', 'status']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_hidden']);
            $table->dropIndex(['visibility']);
            
            // Drop columns
            $table->dropColumn([
                'is_hidden', 'visibility', 'client_approved', 'component_id', 
                'phase_id', 'title', 'assigned_to', 'spent_hours', 'parent_id', 
                'order', 'conditional_tag', 'created_by', 'updated_by', 'watchers'
            ]);
        });
    }
};