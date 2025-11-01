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
        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->string('project_id');
            $table->string('user_id');
            $table->string('action'); // created, updated, deleted, milestone_completed, task_updated, etc.
            $table->string('entity_type'); // Project, Task, Milestone, Document, etc.
            $table->string('entity_id')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable(); // Additional data
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['project_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'entity_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_activities');
    }
};