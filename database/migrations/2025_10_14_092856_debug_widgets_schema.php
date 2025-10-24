<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // Check if widgets table exists and show its structure
        if (Schema::hasTable('widgets')) {
            if (DBDriver::isMysql()) {
                $columns = DB::select("DESCRIBE widgets");
            } else {
                // SQLite equivalent
                $columns = DB::select("PRAGMA table_info(widgets)");
            }
            Log::info('Widgets table structure', ['columns' => $columns]);
        }
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
