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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('created_by')->nullable()->after('pm_id');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('projects') || !Schema::hasColumn('projects', 'created_by')) {
            return;
        }

        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            Schema::table('projects', function (Blueprint $table) {
                try {
                    $table->dropForeign(['created_by']);
                } catch (\Throwable $e) {
                    // no-op for idempotent rollback
                }
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
