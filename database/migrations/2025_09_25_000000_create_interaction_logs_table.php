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
        if (Schema::hasTable('interaction_logs')) {
            return;
        }

        Schema::create('interaction_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('project_id')->nullable();
            $table->string('task_id')->nullable();
            $table->string('linked_task_id')->nullable();
            $table->string('component_id')->nullable();
            $table->string('type')->nullable();
            $table->text('content')->nullable();
            $table->text('description')->nullable();
            $table->string('tag_path')->nullable();
            $table->string('visibility')->nullable();
            $table->boolean('client_approved')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'tenant_id'], 'interaction_logs_project_tenant_index');
            $table->index(['user_id', 'created_at'], 'interaction_logs_user_created_index');
            $table->index('component_id', 'interaction_logs_component_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interaction_logs');
    }
};
