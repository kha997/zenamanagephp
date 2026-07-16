<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id')->nullable();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->string('event_key');
            $table->ulid('actor_user_id')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at');

            $table->foreign('tenant_id', 'event_records_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'event_records_project_id_foreign')
                ->references('id')->on('projects')->nullOnDelete();
            $table->foreign('actor_user_id', 'event_records_actor_user_id_foreign')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'event_key'], 'event_records_tenant_event_key_index');
            $table->index(['aggregate_type', 'aggregate_id'], 'event_records_aggregate_index');
            $table->index(['project_id'], 'event_records_project_index');
            $table->index(['actor_user_id'], 'event_records_actor_index');
            $table->index(['occurred_at'], 'event_records_occurred_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_records');
    }
};
