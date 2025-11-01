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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->enum('lifecycle_stage', ['lead', 'prospect', 'customer', 'inactive'])->default('lead');
            $table->text('notes')->nullable();
            $table->json('address')->nullable(); // Store address as JSON
            $table->json('custom_fields')->nullable(); // Store custom fields as JSON
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'lifecycle_stage']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'created_at']);
            
            // Foreign key constraint (commented out for now)
            // $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
