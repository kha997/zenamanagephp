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
        Schema::create('template_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('set_id');
            $table->string('code');
            $table->string('name');
            $table->integer('order_index')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('set_id')->references('id')->on('template_sets')->onDelete('cascade');

            // Indexes
            $table->index('set_id');
            $table->index('code');
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_phases');
    }
};

