<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Initialize order values and set version=1 for existing tasks
     */
    public function up(): void
    {
        // Initialize version = 1 for all existing tasks
        DB::table('tasks')->whereNull('version')->orWhere('version', 0)->update(['version' => 1]);

        // Initialize order values using ROW_NUMBER() grouped by (project_id, status)
        // Order by created_at to maintain chronological order
        if (DB::getDriverName() === 'mysql') {
            // MySQL 8.0+ supports window functions
            DB::statement("
                UPDATE tasks t
                INNER JOIN (
                    SELECT 
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY project_id, status 
                            ORDER BY created_at ASC, id ASC
                        ) * 1000000 as new_order
                    FROM tasks
                ) ranked ON t.id = ranked.id
                SET t.order = ranked.new_order
                WHERE t.order = 0 OR t.order IS NULL
            ");
        } elseif (DB::getDriverName() === 'pgsql') {
            // PostgreSQL supports window functions
            DB::statement("
                UPDATE tasks t
                SET \"order\" = ranked.new_order
                FROM (
                    SELECT 
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY project_id, status 
                            ORDER BY created_at ASC, id ASC
                        ) * 1000000 as new_order
                    FROM tasks
                ) ranked
                WHERE t.id = ranked.id
                AND (t.\"order\" = 0 OR t.\"order\" IS NULL)
            ");
        } else {
            // For SQLite and other databases, use a simpler approach
            // Get all tasks grouped by project_id and status, ordered by created_at
            $tasks = DB::table('tasks')
                ->select('id', 'project_id', 'status', 'created_at')
                ->orderBy('project_id')
                ->orderBy('status')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $order = 1000000;
            $currentGroup = null;

            foreach ($tasks as $task) {
                $groupKey = $task->project_id . '|' . $task->status;
                
                // Reset order counter when group changes
                if ($currentGroup !== $groupKey) {
                    $order = 1000000;
                    $currentGroup = $groupKey;
                }

                // Update task order
                DB::table('tasks')
                    ->where('id', $task->id)
                    ->update([
                        'order' => $order,
                        'version' => 1
                    ]);

                $order += 1000000;
            }
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: We don't reverse order initialization as it's a data migration
     */
    public function down(): void
    {
        // Reset order to 0 for all tasks
        DB::table('tasks')->update(['order' => 0]);
        
        // Reset version to 1 (keep it as is, since it's the default)
        DB::table('tasks')->update(['version' => 1]);
    }
};
