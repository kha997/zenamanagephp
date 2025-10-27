<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('templates')) {
            Schema::create('templates', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category')->default('general');
                $table->json('structure')->nullable(); // JSON structure for phases, tasks, workflows
                $table->boolean('is_active')->default(true);
                $table->string('created_by');
                $table->foreignUlid('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'category']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('template_versions')) {
            Schema::create('template_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('template_id')->constrained('templates')->onDelete('cascade');
            $table->integer('version')->default(1);
            $table->json('json_body');
            $table->text('note')->nullable();
            $table->string('created_by');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['template_id', 'version']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('templates');
    }
};
