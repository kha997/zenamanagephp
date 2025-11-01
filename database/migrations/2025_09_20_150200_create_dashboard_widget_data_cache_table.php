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
        Schema::create('dashboard_widget_data_cache', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('widget_id')->nullable();
            $table->ulid('user_id')->nullable();
            $table->ulid('project_id')->nullable();
            $table->ulid('tenant_id');
            $table->string('cache_key');
            $table->json('data');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('widget_id')->references('id')->on('dashboard_widgets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            $table->index(['cache_key', 'expires_at']);
            $table->index(['widget_id', 'user_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_data_cache');
    }
};
