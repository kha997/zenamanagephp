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
        if (!Schema::hasTable('project_team_members')) {
            Schema::create('project_team_members', function (Blueprint $table) {
            $table->id();
            $table->string('project_id');
            $table->string('user_id');
            $table->string('role')->default('member'); // project_manager, member, viewer
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['project_id', 'user_id']);
            $table->index(['user_id', 'project_id']);
            $table->index('role');
            
            // Unique constraint để tránh duplicate
            $table->unique(['project_id', 'user_id'], 'unique_project_user');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_team_members');
    }
};