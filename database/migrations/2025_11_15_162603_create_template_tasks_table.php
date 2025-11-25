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
        Schema::create('template_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('set_id');
            $table->string('phase_id');
            $table->string('discipline_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('est_duration_days')->nullable();
            $table->string('role_key')->nullable();
            $table->string('deliverable_type')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_optional')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('set_id')->references('id')->on('template_sets')->onDelete('cascade');
            $table->foreign('phase_id')->references('id')->on('template_phases')->onDelete('cascade');
            $table->foreign('discipline_id')->references('id')->on('template_disciplines')->onDelete('cascade');

            // Indexes
            $table->index('set_id');
            $table->index('code');
            $table->index('phase_id');
            $table->index('discipline_id');
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_tasks');
    }
};

