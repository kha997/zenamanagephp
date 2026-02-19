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
        Schema::create('dashboard_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->nullable()->index();
            $table->string('metric_code')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->string('type')->nullable();
            $table->string('category')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
            $table->json('calculation_config')->nullable();
            $table->json('display_config')->nullable();
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->double('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dashboard_metrics');
    }
};
