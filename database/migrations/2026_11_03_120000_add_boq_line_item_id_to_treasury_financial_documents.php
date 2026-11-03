<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-105 B-1 (Owner Gate 2): an expense may name the BOQ line it pays for. Nullable, additive; a BOQ line that a
 * Treasury document uses cannot be deleted (restrict). The same-project rule lives in TreasuryExpenseService.
 *
 * SQLite: a foreign key added through Blueprint makes Laravel rebuild the table, which would drop the native CHECK
 * constraints GAP-038 put on treasury_financial_documents. SQLite accepts a column-level REFERENCES clause in
 * ALTER TABLE ADD COLUMN without a rebuild, so that form is used there.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('treasury_financial_documents', 'boq_line_item_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE treasury_financial_documents ADD COLUMN boq_line_item_id varchar REFERENCES boq_line_items(id) ON DELETE RESTRICT');
            DB::statement('CREATE INDEX tfd_boq_line_item_idx ON treasury_financial_documents (boq_line_item_id)');

            return;
        }

        Schema::table('treasury_financial_documents', function (Blueprint $table): void {
            $table->ulid('boq_line_item_id')->nullable()->after('expense_plan');
            $table->index('boq_line_item_id', 'tfd_boq_line_item_idx');
            $table->foreign('boq_line_item_id', 'tfd_boq_line_item_fk')->references('id')->on('boq_line_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('treasury_financial_documents', 'boq_line_item_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS tfd_boq_line_item_idx');
            DB::statement('ALTER TABLE treasury_financial_documents DROP COLUMN boq_line_item_id');

            return;
        }

        Schema::table('treasury_financial_documents', function (Blueprint $table): void {
            $table->dropForeign('tfd_boq_line_item_fk');
            $table->dropIndex('tfd_boq_line_item_idx');
            $table->dropColumn('boq_line_item_id');
        });
    }
};
