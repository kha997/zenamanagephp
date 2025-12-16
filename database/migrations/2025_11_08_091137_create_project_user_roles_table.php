<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates pivot table for project-user-role relationships
     * Used by Project model's users() and projectUsers() relationships
     */
    public function up(): void
    {
        if (!Schema::hasTable('project_user_roles')) {
            Schema::create('project_user_roles', function (Blueprint $table) {
                $table->string('project_id');
                $table->string('user_id');
                $table->string('role_id');
                $table->timestamps();

                // Composite primary key
                $table->primary(['project_id', 'user_id', 'role_id'], 'project_user_role_primary');

                // Foreign keys
                $table->foreign('project_id')
                    ->references('id')
                    ->on('projects')
                    ->onDelete('cascade');
                
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
                
                $table->foreign('role_id')
                    ->references('id')
                    ->on('zena_roles')
                    ->onDelete('cascade');

                // Indexes for performance
                $table->index(['project_id', 'user_id']);
                $table->index(['user_id', 'project_id']);
                $table->index(['role_id']);
                $table->index(['project_id', 'role_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user_roles');
    }
};
