<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-048 §9 — remove the DB-level `DEFAULT 'architecture'` safety net and
 * make `service_category` nullable. Historical data is not touched or
 * reclassified; only the mechanism producing new false values is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('service_category')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // GAP-048: once this migration's up() has shipped, NULL
        // service_category is an expected, legitimately-created value (the
        // whole point of this migration) — every writer this Work ID
        // touches (store()/Lead convert()/update()) can now persist it.
        // A rollback that blindly re-adds NOT NULL would crash on real
        // MySQL against any row that has legitimately gone NULL since
        // up() ran (SQLSTATE 22004). Backfill NULLs to the historical
        // 'architecture' default FIRST so the column alteration itself
        // never fails, then restore the NOT NULL + default.
        DB::table('opportunities')->whereNull('service_category')->update(['service_category' => 'architecture']);

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('service_category')->default('architecture')->change();
        });
    }
};
