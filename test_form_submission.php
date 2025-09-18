<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Web\TaskController;
use App\Http\Requests\TaskFormRequest;
use App\Services\TaskService;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== FORM SUBMISSION TEST ===\n\n";

try {
    // Create test form data
    $formData = [
        'title' => 'task 01',
        'description' => 'test description',
        'project_id' => '01k5e2kkwynze0f37a8a4d3435', // Office Building Complex
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
    
    echo "1. Testing TaskFormRequest validation...\n";
    
    // Create request object
    $request = new Request($formData);
    $request->setMethod('POST');
    
    // Test validation
    $formRequest = new TaskFormRequest();
    $formRequest->setContainer($app);
    $formRequest->setRedirector($app->make('redirect'));
    
    // Manually validate
    $validator = $app->make('validator')->make($formData, $formRequest->rules(), $formRequest->messages());
    
    if ($validator->fails()) {
        echo "❌ Validation failed:\n";
        foreach ($validator->errors()->all() as $error) {
            echo "   - {$error}\n";
        }
    } else {
        echo "✅ Validation passed\n";
    }
    
    echo "\n2. Testing TaskService createTask...\n";
    
    // Test TaskService
    $taskService = new TaskService();
    $task = $taskService->createTask($formData);
    
    echo "✅ Task created successfully:\n";
    echo "   - ID: {$task->id}\n";
    echo "   - Name: {$task->name}\n";
    echo "   - Status: {$task->status}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END TEST ===\n";
