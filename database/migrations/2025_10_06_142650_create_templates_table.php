<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->json('structure')->nullable(); // JSON structure for phases, tasks, workflows
            $table->boolean('is_active')->default(true);
            $table->string('created_by');
            $table->foreignUlid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('templates');
    }
};