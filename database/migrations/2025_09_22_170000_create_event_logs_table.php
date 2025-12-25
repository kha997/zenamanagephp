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
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->nullable();
            $table->string('event_name')->index();
            $table->string('event_class')->nullable();
            $table->string('entity_id')->nullable();
            $table->string('project_id')->nullable();
            $table->string('actor_id')->nullable()->index();
            $table->string('tenant_id')->nullable();
            $table->json('payload')->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('source_module')->nullable();
            $table->string('severity', 20)->default('info');
            $table->timestamp('event_timestamp')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
