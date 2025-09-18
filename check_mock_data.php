<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MOCK DATA VERIFICATION ===\n\n";

try {
    // Check users
    $userCount = User::where('email', 'LIKE', '%@test.com')->count();
    echo "1. Test Users: {$userCount}\n";
    
    $users = User::where('email', 'LIKE', '%@test.com')->get();
    foreach ($users as $user) {
        echo "   - {$user->name} ({$user->email}) - {$user->job_title}\n";
    }
    
    // Check projects
    $projectCount = Project::where('name', 'LIKE', '%Development%')
        ->orWhere('name', 'LIKE', '%Analytics%')
        ->orWhere('name', 'LIKE', '%Integration%')
        ->orWhere('name', 'LIKE', '%Audit%')
        ->count();
    echo "\n2. Test Projects: {$projectCount}\n";
    
    $projects = Project::where('name', 'LIKE', '%Development%')
        ->orWhere('name', 'LIKE', '%Analytics%')
        ->orWhere('name', 'LIKE', '%Integration%')
        ->orWhere('name', 'LIKE', '%Audit%')
        ->get();
    foreach ($projects as $project) {
        echo "   - {$project->name} ({$project->status}) - Progress: {$project->progress}%\n";
    }
    
    // Check tasks
    $taskCount = Task::where('name', 'LIKE', '%Design%')
        ->orWhere('name', 'LIKE', '%Development%')
        ->orWhere('name', 'LIKE', '%Integration%')
        ->orWhere('name', 'LIKE', '%Testing%')
        ->orWhere('name', 'LIKE', '%Analysis%')
        ->orWhere('name', 'LIKE', '%Architecture%')
        ->orWhere('name', 'LIKE', '%Security%')
        ->orWhere('name', 'LIKE', '%Compliance%')
        ->count();
    echo "\n3. Test Tasks: {$taskCount}\n";
    
    $tasks = Task::where('name', 'LIKE', '%Design%')
        ->orWhere('name', 'LIKE', '%Development%')
        ->orWhere('name', 'LIKE', '%Integration%')
        ->orWhere('name', 'LIKE', '%Testing%')
        ->orWhere('name', 'LIKE', '%Analysis%')
        ->orWhere('name', 'LIKE', '%Architecture%')
        ->orWhere('name', 'LIKE', '%Security%')
        ->orWhere('name', 'LIKE', '%Compliance%')
        ->with('project')
        ->get();
    
    foreach ($tasks as $task) {
        $projectName = $task->project ? $task->project->name : 'No Project';
        echo "   - {$task->name} ({$task->status}) - {$projectName} - Progress: {$task->progress_percent}%\n";
    }
    
    echo "\n✅ Mock data verification completed!\n";
    echo "📊 Total: {$userCount} users, {$projectCount} projects, {$taskCount} tasks\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END VERIFICATION ===\n";
