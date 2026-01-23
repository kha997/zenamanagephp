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
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable();
            $table->ulid('user_id');
            $table->ulid('project_id')->nullable();
            $table->string('event_key');
            $table->string('min_priority')->default('normal');
            $table->json('channels');
            $table->json('conditions')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('project_id');
            $table->index('event_key');
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
