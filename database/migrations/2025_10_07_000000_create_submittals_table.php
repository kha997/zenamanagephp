<?php declare(strict_types=1);

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
    public function up(): void
    {
        Schema::create('submittals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->nullable()->index();
            $table->ulid('project_id');

            $table->ulid('created_by')->nullable();
            $table->ulid('submitted_by')->nullable();
            $table->ulid('reviewed_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->ulid('rejected_by')->nullable();

            $table->string('submittal_number')->nullable();
            $table->string('submittal_type')->nullable();
            $table->string('package_no')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('specification_section')->nullable();
            $table->string('contractor')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('file_url')->nullable();
            $table->enum('status', ['draft', 'submitted', 'pending_review', 'approved', 'rejected', 'revised'])
                ->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('review_comments')->nullable();
            $table->text('approval_comments')->nullable();
            $table->text('rejection_comments')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->index(['project_id', 'status']);
            $table->index(['submittal_number']);
        });

        Schema::table('submittals', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('submittals');
    }
};
