<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * GAP-079 (AUD-28): RoleSeeder used to create permissions without a name,
 * and later seeders key on code so they never filled it in. Fill name with
 * the code (the value PermissionSeeder writes) for every row still NULL.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'name')) {
            return;
        }

        DB::table('permissions')
            ->whereNull('name')
            ->whereNotNull('code')
            ->update(['name' => DB::raw('code')]);
    }

    public function down(): void
    {
        // Data-only backfill; the previous NULL values carry no information.
    }
};
