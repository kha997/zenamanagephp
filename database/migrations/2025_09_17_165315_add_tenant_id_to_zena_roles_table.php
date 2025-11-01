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
        Schema::table('zena_roles', function (Blueprint $table) {
            $table->ulid('tenant_id')->nullable()->after('is_active');
            $table->index(['tenant_id', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('zena_roles', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'scope']);
            $table->dropColumn('tenant_id');
        });
    }
};
