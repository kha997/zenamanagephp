<?php declare(strict_types=1);

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
        Schema::create('query_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query_hash', 64)->index();
            $table->text('sql');
            $table->json('bindings')->nullable();
            $table->decimal('execution_time', 8, 3); // milliseconds
            $table->string('connection', 50)->default('mysql');
            $table->string('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->integer('memory_usage')->nullable(); // bytes
            $table->integer('rows_affected')->nullable();
            $table->integer('rows_returned')->nullable();
            $table->enum('query_type', ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'OTHER'])->default('OTHER');
            $table->boolean('is_slow')->default(false);
            $table->boolean('is_error')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['executed_at', 'is_slow']);
            $table->index(['user_id', 'executed_at']);
            $table->index(['query_type', 'execution_time']);
            $table->index(['connection', 'execution_time']);
            $table->index(['is_error', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_logs');
    }
};