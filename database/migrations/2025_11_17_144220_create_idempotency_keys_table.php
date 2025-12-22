<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates table to store idempotency keys for critical operations.
     * Prevents duplicate processing of the same request.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->string('route');
            $table->string('method', 10); // GET, POST, PUT, PATCH, DELETE
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Composite index for quick lookups
            $table->index(['tenant_id', 'idempotency_key']);
            $table->index(['user_id', 'idempotency_key']);
            $table->index(['route', 'method']);
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
