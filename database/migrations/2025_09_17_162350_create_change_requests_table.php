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
        Schema::create('change_requests', function (Blueprint $table) {
            $table->string('id')->primary(); // ULID
            $table->string('tenant_id'); // ULID
            $table->string('project_id'); // ULID
            $table->string('task_id')->nullable(); // ULID
            $table->string('change_number')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('change_type'); // scope, schedule, cost, quality, risk, resource
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('status')->default('pending'); // pending, approved, rejected, implemented
            $table->string('impact_level')->default('low'); // low, medium, high
            $table->string('requested_by'); // ULID
            $table->string('assigned_to')->nullable(); // ULID
            $table->string('approved_by')->nullable(); // ULID
            $table->string('rejected_by')->nullable(); // ULID
            $table->timestamp('requested_at');
            $table->date('due_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('implemented_at')->nullable();
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->integer('estimated_days')->default(0);
            $table->integer('actual_days')->default(0);
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('implementation_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->json('impact_analysis')->nullable();
            $table->json('risk_assessment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['requested_by', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'priority']);
            $table->index('due_date');
            $table->index('change_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('change_requests');
    }
};
