<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
        if (env('SKIP_DEBUG_WIDGETS_SCHEMA_LOG')
            || (function_exists('app') && app()->environment('testing'))
            || env('APP_ENV') === 'testing') {
            return;
        }
        dd('env', env('SKIP_DEBUG_WIDGETS_SCHEMA_LOG'), env('APP_ENV'));

        // Check if widgets table exists and show its structure
        if (Schema::hasTable('widgets')) {
            if (DBDriver::isMysql()) {
                $columns = DB::select("DESCRIBE widgets");
            } else {
                // SQLite equivalent
                $columns = DB::select("PRAGMA table_info(widgets)");
            }
            try {
            error_log('Widgets table structure: ' . json_encode($columns, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } catch (\Throwable $e) {
                // Avoid blocking tests if the default logger can't write to storage.
            }
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
