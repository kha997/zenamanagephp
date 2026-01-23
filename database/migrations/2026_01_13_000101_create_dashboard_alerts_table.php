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
        Schema::create('dashboard_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('user_id');
            $table->string('tenant_id');
            $table->string('project_id')->nullable();
            $table->string('widget_id')->nullable();
            $table->string('metric_id')->nullable();
            $table->string('type');
            $table->string('severity')->default('info');
            $table->string('category')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_alerts');
    }
};
