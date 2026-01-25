<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const COLUMNS_TO_COPY = [
        'tenant_id',
        'project_id',
        'created_by',
        'submitted_by',
        'reviewed_by',
        'approved_by',
        'rejected_by',
        'submittal_number',
        'submittal_type',
        'package_no',
        'title',
        'description',
        'specification_section',
        'contractor',
        'manufacturer',
        'file_url',
        'status',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'review_notes',
        'review_comments',
        'approval_comments',
        'rejection_comments',
        'attachments',
        'created_at',
        'updated_at',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('submittals')) {
            return;
        }

        Schema::create('submittals_ulid', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();

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

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['project_id', 'status']);
            $table->index(['submittal_number']);
        });

        $this->copyExistingRows();

        Schema::rename('submittals', 'submittals_legacy');
        Schema::rename('submittals_ulid', 'submittals');
    }

    public function down(): void
    {
        if (Schema::hasTable('submittals')) {
            Schema::drop('submittals');
        }

        if (Schema::hasTable('submittals_legacy')) {
            Schema::rename('submittals_legacy', 'submittals');
        }
    }

    private function copyExistingRows(): void
    {
        DB::table('submittals')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                $payload = [];

                foreach ($rows as $row) {
                    $entry = [
                        'id' => (string) Str::ulid(),
                        'legacy_id' => $row->id,
                    ];

                    foreach (self::COLUMNS_TO_COPY as $column) {
                        $entry[$column] = $row->{$column};
                    }

                    $payload[] = $entry;
                }

                if (!empty($payload)) {
                    DB::table('submittals_ulid')->insert($payload);
                }
            });
    }
};
