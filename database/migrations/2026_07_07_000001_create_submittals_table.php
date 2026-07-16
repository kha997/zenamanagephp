<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('submittals')) {
            return;
        }

        Schema::create('submittals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->ulid('project_id');
            $table->string('submittal_number')->nullable();
            $table->string('package_no')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('submittal_type')->default('other');
            $table->string('specification_section')->nullable();
            $table->string('status')->default('draft');
            $table->date('due_date')->nullable();
            $table->string('contractor')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('file_url')->nullable();
            $table->ulid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->ulid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->text('review_notes')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comments')->nullable();
            $table->ulid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('rejection_comments')->nullable();
            $table->ulid('created_by')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submittals');
    }
};
