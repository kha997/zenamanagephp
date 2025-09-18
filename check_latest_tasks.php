<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== LATEST TASKS CHECK ===\n\n";

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
    
    // Check if task 03 and 04 exist
    $task03 = Task::where('name', 'task 03')->first();
    $task04 = Task::where('name', 'task 04')->first();
    
    if ($task03) {
        echo "✅ Task 03 found in database\n";
    } else {
        echo "❌ Task 03 NOT found in database\n";
    }
    
    if ($task04) {
        echo "✅ Task 04 found in database\n";
    } else {
        echo "❌ Task 04 NOT found in database\n";
    }
    
    // Check total count
    $totalTasks = Task::count();
    echo "Total tasks: {$totalTasks}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END CHECK ===\n";
