<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_appointments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->foreignUlid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->string('type', 20);
            $table->dateTime('scheduled_at');
            $table->string('location', 255)->nullable();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('scheduled');
            $table->text('outcome_notes')->nullable();
            $table->foreignUlid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['tenant_id', 'opportunity_id'], 'opp_appt_tenant_opp_index');
            $table->index(['tenant_id', 'status'], 'opp_appt_tenant_status_index');
            $table->index(['opportunity_id', 'scheduled_at'], 'opp_appt_opp_sched_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_appointments');
    }
};
