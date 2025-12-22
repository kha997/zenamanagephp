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
        if (!Schema::hasTable('user_dashboards')) {
            Schema::create('user_dashboards', function (Blueprint $table) {
                $table->id();
                $table->string('user_id');
                $table->string('tenant_id');
                $table->string('name');
                $table->json('layout_config')->nullable();
                $table->json('widgets')->nullable();
                $table->json('preferences')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['user_id', 'is_default', 'is_active']);
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_dashboards');
    }
};