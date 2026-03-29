<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('code', 100);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('boq_line_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('boq_id');
            $table->ulid('component_id')->nullable();
            $table->string('code', 100)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 50)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('boq_id')->references('id')->on('boqs')->cascadeOnDelete();
            $table->foreign('component_id')->references('id')->on('components')->nullOnDelete();
            $table->index(['tenant_id', 'boq_id']);
            $table->index(['tenant_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_line_items');
        Schema::dropIfExists('boqs');
    }
};
