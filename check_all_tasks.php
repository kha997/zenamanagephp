<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ALL TASKS CHECK ===\n\n";

try {
    // Get all tasks
    $tasks = Task::all();
    
    echo "All tasks in database:\n";
    foreach ($tasks as $task) {
        echo "- ID: {$task->id}\n";
        echo "  Name: {$task->name}\n";
        echo "  Status: {$task->status}\n";
        echo "  Created: {$task->created_at}\n";
        echo "---\n";
    }
    
    // Check total count
    $totalTasks = Task::count();
    echo "Total tasks: {$totalTasks}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END CHECK ===\n";
