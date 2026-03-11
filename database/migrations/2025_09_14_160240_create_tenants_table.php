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
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->string('database_name')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('trial');
            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('zena_audit_logs') && Schema::hasColumn('zena_audit_logs', 'tenant_id')) {
            try {
                Schema::table('zena_audit_logs', function (Blueprint $table) {
                    $table->dropForeign('audit_logs_tenant_id_foreign');
                });
            } catch (\Throwable $e) {
                // Ignore if FK is already absent or named differently in partial rollback states.
            }

            try {
                Schema::table('zena_audit_logs', function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
            } catch (\Throwable $e) {
                // Ignore if FK is already absent or named differently in partial rollback states.
            }
        }

        Schema::dropIfExists('tenants');
    }
};
