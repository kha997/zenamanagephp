<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\DBDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('zena_documents', function (Blueprint $table) {
            $table->foreign('parent_document_id')->references('id')->on('zena_documents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('zena_documents', function (Blueprint $table) {
            if (DBDriver::isMysql()) {
                $table->dropForeign(['parent_document_id']);
            }
        });
    }
};
