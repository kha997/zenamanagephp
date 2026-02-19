<?php

declare(strict_types=1);

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
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('project_id')->nullable();
            $table->string('event_key');
            $table->string('min_priority')->default('normal');
            $table->json('channels');
            $table->boolean('is_enabled')->default(true);
            $table->json('conditions')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'notification_rules_user_id_foreign')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('project_id', 'notification_rules_project_id_foreign')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            $table->index(['user_id', 'event_key']);
            $table->index(['project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
