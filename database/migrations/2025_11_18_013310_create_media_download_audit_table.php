<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates table to audit file downloads for compliance and security.
     */
    public function up(): void
    {
        Schema::create('media_download_audit', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable(false)->index();
            $table->string('user_id')->nullable(false)->index();
            $table->string('file_id')->nullable(false)->index();
            $table->string('file_type')->nullable(false); // 'file' or 'document'
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_name')->nullable();
            $table->string('ip_address', 45)->nullable(); // IPv6 support
            $table->text('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->string('download_method')->default('direct'); // direct, signed_url, ttl_link
            $table->string('trace_id')->nullable()->index(); // X-Request-Id for correlation
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['tenant_id', 'created_at']);
            $table->index(['file_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
            
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
        Schema::dropIfExists('media_download_audit');
    }
};
