<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_diaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('diary_number')->unique();
            $table->date('diary_date');
            $table->string('weather')->nullable();
            $table->string('temperature')->nullable();
            $table->unsignedInteger('manpower_count')->default(0);
            $table->text('work_performed');
            $table->text('equipment_used')->nullable();
            $table->text('materials_delivered')->nullable();
            $table->text('safety_notes')->nullable();
            $table->text('visitors')->nullable();
            $table->text('delays_issues')->nullable();
            $table->string('status')->default('draft'); // draft|submitted|approved
            $table->ulid('created_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'site_diaries_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'site_diaries_project_id_foreign')
                ->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('created_by', 'site_diaries_created_by_foreign')
                ->references('id')->on('users');
            $table->foreign('approved_by', 'site_diaries_approved_by_foreign')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(['project_id', 'diary_date'], 'site_diaries_project_date_unique');
            $table->index(['tenant_id', 'status'], 'site_diaries_tenant_status_index');
            $table->index(['diary_date'], 'site_diaries_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_diaries');
    }
};
