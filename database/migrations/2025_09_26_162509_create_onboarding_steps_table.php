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
        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('type'); // tooltip, modal, tour, interactive
            $table->string('target_element')->nullable(); // CSS selector for tooltip/modal
            $table->string('position')->nullable(); // top, bottom, left, right for tooltips
            $table->json('content')->nullable(); // Additional content data
            $table->json('actions')->nullable(); // Available actions (next, skip, etc.)
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->string('role')->nullable(); // Specific role requirement
            $table->timestamps();
            
            $table->index(['is_active', 'order']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('onboarding_steps');
    }
};
