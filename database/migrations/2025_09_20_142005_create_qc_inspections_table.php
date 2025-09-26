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
        Schema::create('qc_inspections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('qc_plan_id');
            $table->ulid('tenant_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'failed'])->default('scheduled');
            $table->date('inspection_date');
            $table->ulid('inspector_id');
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->json('checklist_results')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['qc_plan_id', 'status']);
            $table->index(['tenant_id']);
            $table->index(['inspector_id']);
            $table->index(['inspection_date']);

            // Foreign Keys
            $table->foreign('qc_plan_id')->references('id')->on('qc_plans')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('inspector_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('qc_inspections');
    }
};