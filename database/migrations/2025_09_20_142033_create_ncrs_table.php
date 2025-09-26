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
        Schema::create('ncrs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->ulid('tenant_id');
            $table->ulid('inspection_id')->nullable();
            $table->string('ncr_number')->unique();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['open', 'under_review', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->ulid('created_by');
            $table->ulid('assigned_to')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['project_id', 'status']);
            $table->index(['tenant_id']);
            $table->index(['inspection_id']);
            $table->index(['created_by']);
            $table->index(['assigned_to']);
            $table->index(['severity']);
            $table->index(['ncr_number']);

            // Foreign Keys
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('inspection_id')->references('id')->on('qc_inspections')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ncrs');
    }
};