<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use App\Services\TaskService;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TASK CREATION TEST ===\n\n";

try {
    // Get first project
    $project = Project::first();
    echo "1. Using project: ID: {$project->id}, Name: {$project->name}\n";
    
    // Test data
    $taskData = [
        'project_id' => $project->id,
        'title' => 'Test Task Creation',
        'description' => 'This is a test task to verify creation works',
        'priority' => 'medium',
        'status' => 'pending',
        'start_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(7)->format('Y-m-d'),
        'estimated_hours' => 8,
        'assignee_id' => null,
        'tags' => 'test,debug'
    ];
    
    echo "2. Task data:\n";
    foreach ($taskData as $key => $value) {
        echo "   - {$key}: {$value}\n";
    }
    
    // Create task using TaskService
    echo "3. Creating task using TaskService...\n";
    $taskService = new TaskService();
    $task = $taskService->createTask($taskData);
    
    echo "4. Task created successfully!\n";
    echo "   - ID: {$task->id}\n";
    echo "   - Name: {$task->name}\n";
    echo "   - Status: {$task->status}\n";
    echo "   - Created: {$task->created_at}\n";
    
    // Verify task is in database
    $dbTask = Task::find($task->id);
    if ($dbTask) {
        echo "5. Task verified in database: YES\n";
    } else {
        echo "5. Task verified in database: NO\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END TEST ===\n";
