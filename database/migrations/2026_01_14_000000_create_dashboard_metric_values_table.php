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
        Schema::create('dashboard_metric_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('metric_id');
            $table->string('tenant_id');
            $table->string('project_id')->nullable();
            $table->float('value');
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_metric_values');
    }
};
