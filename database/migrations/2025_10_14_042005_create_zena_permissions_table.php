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
        if (!Schema::hasTable('zena_permissions')) {
            Schema::create('zena_permissions', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('code')->unique();
                $table->string('module');
                $table->string('action');
                $table->text('description')->nullable();
                $table->timestamps();
                
                $table->index(['module', 'action']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zena_permissions');
    }
};