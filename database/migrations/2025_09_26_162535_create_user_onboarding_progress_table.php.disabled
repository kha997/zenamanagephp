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
        Schema::create('user_onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('onboarding_step_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, completed, skipped
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->json('data')->nullable(); // Additional progress data
            $table->timestamps();
            
            $table->unique(['user_id', 'onboarding_step_id']);
            $table->index(['user_id', 'status']);
            $table->index(['onboarding_step_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_onboarding_progress');
    }
};
