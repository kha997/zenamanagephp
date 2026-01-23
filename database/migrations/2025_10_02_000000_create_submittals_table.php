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
        Schema::create('submittals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_id');
            $table->string('project_id');
            $table->string('submittal_number');
            $table->string('package_no')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('submittal_type');
            $table->string('specification_section')->nullable();
            $table->string('status')->default('draft');
            $table->date('due_date')->nullable();
            $table->string('contractor')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('file_url')->nullable();
            $table->string('created_by');
            $table->string('submitted_by');
            $table->timestamp('submitted_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comments')->nullable();
            $table->string('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('rejection_comments')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['tenant_id']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submittals');
    }
};
