<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Requests\TaskFormRequest;

// Test validation rules
$rules = [
    'project_id' => ['required', 'exists:projects,id'],
    'name' => ['required', 'string', 'max:255'],
    'description' => ['nullable', 'string', 'max:1000'],
    'priority' => ['required', 'in:low,medium,high,urgent'],
    'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
    'start_date' => ['required', 'date'],
    'end_date' => ['required', 'date', 'after_or_equal:start_date'],
    'estimated_hours' => ['nullable', 'numeric', 'min:0'],
    'assignee_id' => ['nullable', 'exists:users,id'],
    'tags' => ['nullable', 'string', 'max:500'],
];

echo "TaskFormRequest validation rules:\n";
foreach ($rules as $field => $rule) {
    echo "- $field: " . implode('|', $rule) . "\n";
}

echo "\nTesting with sample data:\n";

// Sample data that should pass validation
$sampleData = [
    'project_id' => '01k5e5nty3m1059pcyymbkgqt8', // Valid project ID
    'name' => 'task 03',
    'description' => 'task 0303',
    'priority' => 'low',
    'status' => 'in_progress',
    'start_date' => '2025-09-18',
    'end_date' => '2025-09-19',
    'estimated_hours' => 0,
    'assignee_id' => null,
    'tags' => '',
];

foreach ($sampleData as $field => $value) {
    echo "- $field: '$value'\n";
}

echo "\nChecking if project exists in database...\n";

// Check if project exists
try {
    $project = \Src\CoreProject\Models\Project::find('01k5e5nty3m1059pcyymbkgqt8');
    if ($project) {
        echo "✅ Project found: " . $project->name . "\n";
    } else {
        echo "❌ Project not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking project: " . $e->getMessage() . "\n";
}

echo "\nChecking if user exists...\n";
try {
    $users = \App\Models\User::all();
    echo "✅ Found " . $users->count() . " users\n";
    foreach ($users as $user) {
        echo "  - " . $user->id . ": " . $user->name . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking users: " . $e->getMessage() . "\n";
}

?>
