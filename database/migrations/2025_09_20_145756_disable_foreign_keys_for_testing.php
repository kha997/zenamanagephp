<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key constraints for testing
        if (app()->environment('testing')) {
            $driver = DB::getDriverName();
            
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=OFF;');
            } elseif ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-enable foreign key constraints
        if (app()->environment('testing')) {
            $driver = DB::getDriverName();
            
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=ON;');
            } elseif ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }
    }
};