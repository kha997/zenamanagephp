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
        // Z.E.N.A System Tables - Core Business Logic
        
        // Projects table (Z.E.N.A specific)
        Schema::create('zena_projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->ulid('client_id')->nullable();
            $table->enum('status', ['planning', 'active', 'on_hold', 'completed', 'cancelled'])->default('planning');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['status']);
        });

        // Project users pivot table
        Schema::create('zena_project_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->ulid('user_id');
            $table->string('role_on_project')->default('member');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['project_id', 'user_id']);
        });

        // Tasks table
        Schema::create('zena_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->ulid('assignee_id')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('progress')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('assignee_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });

        // Drawings table
        Schema::create('zena_drawings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('code');
            $table->string('name');
            $table->string('version')->default('1.0');
            $table->enum('status', ['draft', 'review', 'approved', 'issued'])->default('draft');
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->ulid('uploaded_by');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['project_id', 'status']);
        });

        // RFIs table
        Schema::create('zena_rfis', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('subject');
            $table->text('question');
            $table->ulid('asked_by');
            $table->ulid('assigned_to')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'answered', 'closed'])->default('open');
            $table->text('answer')->nullable();
            $table->ulid('answered_by')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('asked_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('answered_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'status']);
        });

        // Submittals table
        Schema::create('zena_submittals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('package_no');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft');
            $table->date('due_date')->nullable();
            $table->string('file_url')->nullable();
            $table->ulid('submitted_by');
            $table->ulid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['project_id', 'status']);
        });

        // Change Requests table
        Schema::create('zena_change_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('title');
            $table->text('reason');
            $table->text('impact_description')->nullable();
            $table->decimal('impact_cost', 15, 2)->nullable();
            $table->integer('impact_time_days')->nullable();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft');
            $table->ulid('requested_by');
            $table->ulid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'status']);
        });

        // Material Requests table
        Schema::create('zena_material_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('request_number');
            $table->text('description');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'fulfilled'])->default('draft');
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->date('required_date')->nullable();
            $table->ulid('requested_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'status']);
        });

        // Purchase Orders table
        Schema::create('zena_purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('po_number');
            $table->string('vendor_name');
            $table->text('description');
            $table->enum('status', ['draft', 'sent', 'approved', 'received', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2);
            $table->date('due_date')->nullable();
            $table->ulid('created_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'status']);
        });

        // Invoices table
        Schema::create('zena_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('invoice_number');
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['draft', 'sent', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->date('due_date');
            $table->ulid('created_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'status']);
        });

        // QC Plans table
        Schema::create('zena_qc_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->date('planned_date');
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['project_id', 'status']);
        });

        // QC Inspections table
        Schema::create('zena_qc_inspections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('qc_plan_id');
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'failed'])->default('scheduled');
            $table->date('inspection_date');
            $table->ulid('inspector_id');
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();

            $table->foreign('qc_plan_id')->references('id')->on('zena_qc_plans')->onDelete('cascade');
            $table->foreign('inspector_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['qc_plan_id', 'status']);
        });

        // NCRs table
        Schema::create('zena_ncrs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('ncr_number');
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['open', 'under_review', 'closed'])->default('open');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->ulid('created_by');
            $table->ulid('assigned_to')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('zena_projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'status']);
        });

        // Z.E.N.A Notifications table
        Schema::create('zena_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zena_notifications');
        Schema::dropIfExists('zena_ncrs');
        Schema::dropIfExists('zena_qc_inspections');
        Schema::dropIfExists('zena_qc_plans');
        Schema::dropIfExists('zena_invoices');
        Schema::dropIfExists('zena_purchase_orders');
        Schema::dropIfExists('zena_material_requests');
        Schema::dropIfExists('zena_change_requests');
        Schema::dropIfExists('zena_submittals');
        Schema::dropIfExists('zena_rfis');
        Schema::dropIfExists('zena_drawings');
        Schema::dropIfExists('zena_tasks');
        Schema::dropIfExists('zena_project_users');
        Schema::dropIfExists('zena_projects');
    }
};
