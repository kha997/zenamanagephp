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
        Schema::create('dashboard_metric_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('metric_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();
            $table->double('value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->nullable();
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
        Schema::dropIfExists('dashboard_metric_values');
    }
};
