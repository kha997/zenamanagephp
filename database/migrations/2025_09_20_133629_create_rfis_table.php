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
        Schema::create('rfis', function (Blueprint $table) {
            $table->string('id')->primary(); // ULID primary key
            $table->string('tenant_id');
            $table->string('project_id');
            $table->string('title');
            $table->string('subject');
            $table->text('description');
            $table->text('question');
            $table->string('rfi_number')->unique();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->string('location')->nullable();
            $table->string('drawing_reference')->nullable();
            $table->string('asked_by');
            $table->string('created_by');
            $table->string('assigned_to')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'pending', 'in_progress', 'answered', 'closed', 'escalated'])->default('open');
            $table->text('answer')->nullable();
            $table->text('response')->nullable();
            $table->string('answered_by')->nullable();
            $table->string('responded_by')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('assignment_notes')->nullable();
            $table->string('escalated_to')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->string('escalated_by')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id']);
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['due_date']);
            $table->index(['priority']);
            $table->index(['created_at']);
        });

        // Add foreign keys
        Schema::table('rfis', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('asked_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('answered_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('responded_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('escalated_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('escalated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('closed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rfis');
    }
};
