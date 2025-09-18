<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== EDIT TASK DEBUG ===\n\n";

try {
    // Find task 03
    $task = Task::where('name', 'task 03')->first();
    
    if ($task) {
        echo "✅ Task 03 found:\n";
        echo "   - ID: {$task->id}\n";
        echo "   - Name: {$task->name}\n";
        echo "   - Description: {$task->description}\n";
        echo "   - Status: {$task->status}\n";
        echo "   - Priority: {$task->priority}\n";
        echo "   - Project ID: {$task->project_id}\n";
        echo "   - Assignee ID: {$task->assignee_id}\n";
        echo "   - Start Date: {$task->start_date}\n";
        echo "   - End Date: {$task->end_date}\n";
        echo "   - Progress: {$task->progress_percent}\n";
        echo "   - Estimated Hours: {$task->estimated_hours}\n";
        echo "   - Tags: {$task->tags}\n";
        
        // Test formData initialization
        echo "\n📝 FormData would be:\n";
        echo "   - id: '{$task->id}'\n";
        echo "   - title: '{$task->name}'\n";
        echo "   - description: '{$task->description}'\n";
        echo "   - project_id: '{$task->project_id}'\n";
        echo "   - assignee_id: '{$task->assignee_id}'\n";
        echo "   - status: '{$task->status}'\n";
        echo "   - priority: '{$task->priority}'\n";
        echo "   - start_date: '{$task->start_date}'\n";
        echo "   - due_date: '{$task->end_date}'\n";
        echo "   - progress: {$task->progress_percent}\n";
        echo "   - estimated_hours: {$task->estimated_hours}\n";
        echo "   - tags: " . json_encode(explode(',', $task->tags ?? '')) . "\n";
        
    } else {
        echo "❌ Task 03 NOT found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
