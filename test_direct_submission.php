<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Web\TaskController;
use App\Http\Requests\TaskFormRequest;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DIRECT SUBMISSION TEST ===\n\n";

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
    
    echo "1. Testing TaskController store method directly...\n";
    
    // Create request object
    $request = new Request($formData);
    $request->setMethod('POST');
    
    // Create TaskService instance
    $taskService = new \App\Services\TaskService();
    
    // Create TaskController instance
    $taskController = new TaskController($taskService);
    
    // Test store method
    $response = $taskController->store(new TaskFormRequest($formData));
    
    echo "✅ TaskController store method executed successfully\n";
    echo "Response type: " . get_class($response) . "\n";
    
    // Check if task was created
    $task01 = \Src\CoreProject\Models\Task::where('name', 'task 01')->first();
    if ($task01) {
        echo "✅ Task 01 created successfully:\n";
        echo "   - ID: {$task01->id}\n";
        echo "   - Name: {$task01->name}\n";
        echo "   - Status: {$task01->status}\n";
    } else {
        echo "❌ Task 01 not created\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END TEST ===\n";
