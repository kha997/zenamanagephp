<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('user_id');
            $table->string('ticket_number');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->ulid('assigned_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'ticket_number']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
