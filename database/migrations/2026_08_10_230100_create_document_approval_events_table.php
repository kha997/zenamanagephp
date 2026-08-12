<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_approval_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->foreignUlid('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignUlid('document_version_id')->nullable()->constrained('document_versions')->restrictOnDelete();
            $table->string('event', 32);
            $table->string('from_approval_status', 32);
            $table->string('to_approval_status', 32);
            $table->foreignUlid('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->json('context');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approval_events');
    }
};
