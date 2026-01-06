<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        $hasDueDate = Schema::hasColumn('tasks', 'due_date');

        if (! $hasDueDate) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->date('due_date')->nullable()->after('end_date');
            });
        }

        if (Schema::hasColumn('tasks', 'due_date')) {
            $indexExists = ! empty(DB::select(
                'SHOW INDEX FROM tasks WHERE Column_name = ?',
                ['due_date']
            ));

            if (! $indexExists) {
                Schema::table('tasks', function (Blueprint $table) {
                    $table->index('due_date');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        if (! Schema::hasColumn('tasks', 'due_date')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }
};
