<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfi_legacy_migration_confirmations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('rfi_id')->unique();
            $table->ulid('confirmed_by');
            $table->timestamp('confirmed_at');
            $table->string('confirmed_lifecycle_status');
            $table->string('confirmed_escalation_state');
            $table->text('reason');
            $table->json('source_snapshot');
            $table->timestamps();

            $table->foreign('rfi_id')->references('id')->on('rfis')->cascadeOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfi_legacy_migration_confirmations');
    }
};
