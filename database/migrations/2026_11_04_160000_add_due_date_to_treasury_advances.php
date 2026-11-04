<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-108 H-1 (Owner Gate 2): an optional due date on the advance request (treasury_financial_documents, advance type
 * only) and the due date in force on the posted advance (treasury_advances). Nullable, additive.
 *
 * SQLite: a column added through Blueprint can make Laravel rebuild the table, which would drop the native CHECK
 * constraints GAP-038 put on both tables. ALTER TABLE ADD COLUMN keeps them (GAP-105 precedent).
 */
return new class () extends Migration {
    private const TABLES = ['treasury_financial_documents', 'treasury_advances'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'due_date')) {
                continue;
            }
            if (DB::getDriverName() === 'sqlite') {
                DB::statement(sprintf('ALTER TABLE %s ADD COLUMN due_date date NULL', $table));

                continue;
            }
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->date('due_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasColumn($table, 'due_date')) {
                continue;
            }
            if (DB::getDriverName() === 'sqlite') {
                DB::statement(sprintf('ALTER TABLE %s DROP COLUMN due_date', $table));

                continue;
            }
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('due_date');
            });
        }
    }
};
