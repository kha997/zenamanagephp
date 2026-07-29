<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submittal_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->foreignUlid('submittal_id')->constrained('submittals')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->text('revision_summary')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('file_url')->nullable();
            $table->json('attachment_manifest')->nullable();
            $table->ulid('submitted_by')->nullable();
            $table->timestamp('submitted_at');
            $table->string('decision')->nullable();
            $table->ulid('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_comments')->nullable();
            // Nullable rather than a bare NOT NULL timestamp: under strict MySQL/MariaDB (no
            // explicit_defaults_for_timestamp), a second non-nullable TIMESTAMP column with no
            // explicit DEFAULT gets an implicit zero-date default, which NO_ZERO_DATE/strict SQL
            // mode rejects at CREATE TABLE time ("Invalid default value for 'created_at'"). The
            // application always sets this explicitly on insert (SubmittalLifecycleService),
            // so nullable is safe and matches the pattern used by sibling migrations
            // (e.g. 2026_07_17_150000_create_price_reference_entries_table.php).
            $table->timestamp('created_at')->nullable();

            $table->unique(['submittal_id', 'revision_no']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submittal_revisions');
    }
};
