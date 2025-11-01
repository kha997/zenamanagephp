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
        if (!Schema::hasTable('project_teams')) {
            Schema::create('project_teams', function (Blueprint $table) {
                $table->ulid('project_id');
                $table->ulid('team_id');
                $table->string('role')->default('contributor'); // contributor, reviewer, stakeholder
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();

                $table->primary(['project_id', 'team_id']);
                
                // Foreign keys
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
                
                // Indexes
                $table->index(['project_id', 'role']);
                $table->index(['team_id', 'project_id']);
                $table->index(['joined_at']);
                $table->index(['left_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_teams');
    }
};