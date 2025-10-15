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
        Schema::table('widgets', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
        
        Schema::table('widgets', function (Blueprint $table) {
            // Recreate tenant_id column with nullable
            $table->ulid('tenant_id')->nullable()->after('user_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
