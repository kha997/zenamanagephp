<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== RECENT TASKS CHECK ===\n\n";

try {
    // Get latest 5 tasks
    $latestTasks = Task::orderBy('created_at', 'desc')->take(5)->get();
    
    echo "Latest 5 tasks:\n";
    foreach ($latestTasks as $task) {
        echo "- ID: {$task->id}\n";
        echo "  Name: {$task->name}\n";
        echo "  Status: {$task->status}\n";
        echo "  Created: {$task->created_at}\n";
        echo "  Project ID: {$task->project_id}\n";
        echo "---\n";
    }
    
    // Check if task 01 exists
    $task01 = Task::where('name', 'task 01')->first();
    if ($task01) {
        echo "✅ Task 01 found in database\n";
        echo "   - ID: {$task01->id}\n";
        echo "   - Created: {$task01->created_at}\n";
    } else {
        echo "❌ Task 01 NOT found in database\n";
    }
    
    // Check total count
    $totalTasks = Task::count();
    echo "Total tasks: {$totalTasks}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END CHECK ===\n";
