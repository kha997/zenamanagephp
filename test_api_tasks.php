<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;
use App\Services\TaskService;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== API TASKS TEST ===\n\n";

try {
    // Test TaskService getTasks method
    echo "1. Testing TaskService getTasks method...\n";
    $taskService = new TaskService();
    $tasks = $taskService->getTasks([], 15);
    
    echo "   - Total tasks: " . $tasks->total() . "\n";
    echo "   - Current page: " . $tasks->currentPage() . "\n";
    echo "   - Per page: " . $tasks->perPage() . "\n";
    echo "   - Items count: " . $tasks->count() . "\n";
    
    echo "2. Tasks data:\n";
    foreach ($tasks->items() as $task) {
        echo "   - ID: {$task->id}, Name: {$task->name}, Status: {$task->status}\n";
    }
    
    // Test direct database query
    echo "3. Testing direct database query...\n";
    $dbTasks = Task::orderBy('created_at', 'desc')->get();
    echo "   - Direct query count: " . $dbTasks->count() . "\n";
    foreach ($dbTasks as $task) {
        echo "   - ID: {$task->id}, Name: {$task->name}, Status: {$task->status}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END TEST ===\n";
