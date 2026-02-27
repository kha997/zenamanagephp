<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_instance_step_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('work_instance_step_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_instance_step_id')->references('id')->on('work_instance_steps')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'work_instance_step_id'], 'wisa_tenant_step_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_instance_step_attachments');
    }
};
