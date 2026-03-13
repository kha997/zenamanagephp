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
        Schema::table('zena_components', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('zena_components')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('zena_components')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('zena_components', function (Blueprint $table) {
            try {
                $table->dropForeign(['parent_id']);
            } catch (\Throwable $e) {
                // no-op for idempotent rollback
            }
        });
    }
};
