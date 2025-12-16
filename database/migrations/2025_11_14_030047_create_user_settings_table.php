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
        if (!Schema::hasTable('user_settings')) {
            Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->ulid('user_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'key']);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
