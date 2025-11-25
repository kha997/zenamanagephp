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
        Schema::create('dead_letter_queue', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 255)->unique();
            $table->string('job_class', 255)->index();
            $table->text('payload');
            $table->text('exception');
            $table->text('exception_trace')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('queue', 100)->nullable()->index();
            $table->timestamp('failed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Composite index for tenant queries
            $table->index(['tenant_id', 'failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dead_letter_queue');
    }
};

