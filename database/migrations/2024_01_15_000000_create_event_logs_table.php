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
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_name')->index(); // e.g., 'Project.Component.ProgressUpdated'
            $table->string('event_class')->index(); // Full class name
            $table->string('entity_id')->nullable()->index(); // ID của entity chính
            $table->foreignUlid('project_id')->nullable()->index(); // Project context
            $table->foreignUlid('actor_id')->nullable()->index(); // User thực hiện
            $table->foreignUlid('tenant_id')->nullable()->index(); // Tenant isolation
            $table->json('payload'); // Full event payload
            $table->json('changed_fields')->nullable(); // Các field đã thay đổi
            $table->string('source_module', 50)->index(); // Module nguồn
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->timestamp('event_timestamp'); // Thời gian event xảy ra
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['tenant_id', 'project_id', 'created_at']);
            $table->index(['source_module', 'event_name', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};