<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TASK 01 DEBUG ===\n\n";

try {
    // Find task 01
    $task01 = Task::where('name', 'task 01')->first();
    
    if ($task01) {
        echo "1. Task 01 found in database:\n";
        echo "   - ID: {$task01->id}\n";
        echo "   - Name: {$task01->name}\n";
        echo "   - Status: {$task01->status}\n";
        echo "   - Project ID: {$task01->project_id}\n";
        echo "   - Created: {$task01->created_at}\n";
        echo "   - Updated: {$task01->updated_at}\n";
        
        // Check if it's in API response
        echo "\n2. Checking API response...\n";
        $response = file_get_contents('http://localhost:8000/api/tasks');
        $data = json_decode($response, true);
        
        if ($data && isset($data['data']['tasks'])) {
            $found = false;
            foreach ($data['data']['tasks'] as $task) {
                if ($task['name'] === 'task 01') {
                    $found = true;
                    echo "   ✅ Task 01 found in API response\n";
                    break;
                }
            }
            
            if (!$found) {
                echo "   ❌ Task 01 NOT found in API response\n";
                echo "   Total tasks in API: " . count($data['data']['tasks']) . "\n";
                echo "   First few tasks:\n";
                foreach (array_slice($data['data']['tasks'], 0, 3) as $task) {
                    echo "     - {$task['name']} (ID: {$task['id']})\n";
                }
            }
        }
        
    } else {
        echo "1. Task 01 NOT found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
