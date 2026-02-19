<?php

declare(strict_types=1);

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
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->ulid('tenant_id')->nullable()->after('project_id');

            $table->foreign('tenant_id', 'notification_rules_tenant_id_foreign')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id'], 'notification_rules_tenant_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->dropForeign('notification_rules_tenant_id_foreign');
            $table->dropIndex('notification_rules_tenant_id_index');
            $table->dropColumn('tenant_id');
        });
    }
};
