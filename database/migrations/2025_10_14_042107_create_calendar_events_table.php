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
        if (!Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('user_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->boolean('all_day')->default(false);
                $table->ulid('project_id')->nullable();
                $table->datetime('start_time');
                $table->datetime('end_time');
                $table->string('status')->default('scheduled');
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
                
                $table->index(['tenant_id', 'start_time']);
                $table->index(['user_id', 'start_time']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};