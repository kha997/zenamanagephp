<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TASK CREATION DEBUG ===\n\n";

// Check if tasks table exists
try {
    $tableExists = DB::getSchemaBuilder()->hasTable('tasks');
    echo "1. Tasks table exists: " . ($tableExists ? "YES" : "NO") . "\n";
    
    if ($tableExists) {
        // Get table structure
        $columns = DB::getSchemaBuilder()->getColumnListing('tasks');
        echo "2. Tasks table columns: " . implode(', ', $columns) . "\n";
        
        // Count total tasks
        $totalTasks = Task::count();
        echo "3. Total tasks in database: " . $totalTasks . "\n";
        
        // Get latest tasks
        $latestTasks = Task::orderBy('created_at', 'desc')->take(5)->get();
        echo "4. Latest 5 tasks:\n";
        foreach ($latestTasks as $task) {
            echo "   - ID: {$task->id}, Name: {$task->name}, Status: {$task->status}, Created: {$task->created_at}\n";
        }
        
        // Check if there are any tasks with today's date
        $todayTasks = Task::whereDate('created_at', today())->get();
        echo "5. Tasks created today: " . $todayTasks->count() . "\n";
        foreach ($todayTasks as $task) {
            echo "   - ID: {$task->id}, Name: {$task->name}, Status: {$task->status}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
