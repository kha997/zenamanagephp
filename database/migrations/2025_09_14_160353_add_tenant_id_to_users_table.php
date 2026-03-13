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
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'tenant_id')) {
            return;
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        } catch (\Throwable $e) {
            // Ignore missing foreign key in partial rollback states.
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
            });
        } catch (\Throwable $e) {
            // Ignore missing index in partial rollback states.
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        } catch (\Throwable $e) {
            // Ignore missing column changes in partial rollback states.
        }
    }
};
