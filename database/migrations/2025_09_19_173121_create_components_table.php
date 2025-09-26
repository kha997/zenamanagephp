<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('components', function (Blueprint $table) {
            $table->string('id')->primary(); // ULID primary key
            $table->string('project_id');
            $table->string('parent_component_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('component');
            $table->string('status')->default('active');
            $table->integer('sort_order')->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0.00);
            $table->decimal('planned_cost', 15, 2)->default(0.00);
            $table->decimal('actual_cost', 15, 2)->default(0.00);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'sort_order']);
            $table->index(['parent_component_id']);
        });

        // Add foreign keys after table creation
        Schema::table('components', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('parent_component_id')->references('id')->on('components')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('components');
    }
};
