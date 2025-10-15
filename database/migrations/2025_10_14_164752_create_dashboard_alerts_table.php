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
        if (!Schema::hasTable('dashboard_alerts')) {
            Schema::create('dashboard_alerts', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('user_id');
                $table->ulid('tenant_id');
                $table->string('message');
                $table->string('type')->default('info'); // info, warning, error, success
                $table->boolean('is_read')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['user_id', 'is_read']);
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_alerts');
    }
};