<?php

require_once 'vendor/autoload.php';

use App\Services\TaskService;
use Src\CoreProject\Models\Task;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TASK SERVICE TEST ===\n\n";

try {
    // Create test form data
    $formData = [
        'title' => 'task 01',
        'description' => 'test description',
        'project_id' => '01k5e2kkwynze0f37a8a4d3435', // E-Commerce Platform
        'priority' => 'low',
        'status' => 'pending',
        'start_date' => '2025-09-18',
        'due_date' => '2025-09-19',
        'estimated_hours' => 8,
        'assignee_id' => null,
        'watchers' => [],
        'notifications' => true,
        'time_tracking' => true,
        'subtasks' => false,
        'tags' => 'test,debug'
    ];
    
    echo "1. Testing TaskService createTask method directly...\n";
    
    // Create TaskService instance
    $taskService = new TaskService();
    
    // Test createTask method
    $task = $taskService->createTask($formData);
    
    echo "✅ TaskService createTask method executed successfully\n";
    echo "Task created:\n";
    echo "   - ID: {$task->id}\n";
    echo "   - Name: {$task->name}\n";
    echo "   - Status: {$task->status}\n";
    echo "   - Project ID: {$task->project_id}\n";
    
    // Check if task exists in database
    $task01 = Task::where('name', 'task 01')->first();
    if ($task01) {
        echo "✅ Task 01 found in database:\n";
        echo "   - ID: {$task01->id}\n";
        echo "   - Created: {$task01->created_at}\n";
    } else {
        echo "❌ Task 01 not found in database\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END TEST ===\n";
