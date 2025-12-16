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
        Schema::create('job_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 255)->unique();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('action', 100)->index();
            $table->string('status', 20)->default('processing')->index(); // processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Composite index for tenant + action queries
            $table->index(['tenant_id', 'action', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_idempotency_keys');
    }
};

