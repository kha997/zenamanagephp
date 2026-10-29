<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-014c: durable NCR ↔ CAPA task link. A CAPA task records the NCR it corrects; one NCR can have many CAPA tasks.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('tasks', 'ncr_id')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->ulid('ncr_id')->nullable()->after('phase_id');
            $table->index('ncr_id', 'tasks_ncr_id_index');
            $table->foreign('ncr_id', 'tasks_ncr_id_foreign')->references('id')->on('ncrs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tasks', 'ncr_id')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['ncr_id']);
            $table->dropIndex('tasks_ncr_id_index');
            $table->dropColumn('ncr_id');
        });
    }
};
