<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('name');
            $table->string('url');
            $table->string('secret');
            $table->json('event_keys'); // list of event-key prefixes, ['*'] = all
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by');
            $table->timestamp('last_delivered_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'webhook_endpoints_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by', 'webhook_endpoints_created_by_foreign')
                ->references('id')->on('users');

            $table->index(['tenant_id', 'is_active'], 'webhook_endpoints_tenant_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
