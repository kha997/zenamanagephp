<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Project;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROJECTS DEBUG ===\n\n";

try {
    // Check if projects table exists
    $tableExists = DB::getSchemaBuilder()->hasTable('projects');
    echo "1. Projects table exists: " . ($tableExists ? "YES" : "NO") . "\n";
    
    if ($tableExists) {
        // Count total projects
        $totalProjects = Project::count();
        echo "2. Total projects in database: " . $totalProjects . "\n";
        
        // Get all projects
        $projects = Project::all();
        echo "3. All projects:\n";
        foreach ($projects as $project) {
            echo "   - ID: {$project->id}, Name: {$project->name}\n";
        }
        
        // If no projects, create a test project
        if ($totalProjects == 0) {
            echo "4. No projects found. Creating test project...\n";
            $testProject = Project::create([
                'name' => 'Test Project',
                'description' => 'Test project for task creation',
                'status' => 'active',
                'start_date' => now(),
                'end_date' => now()->addMonths(6),
                'budget' => 100000,
                'client_id' => null,
                'manager_id' => null,
            ]);
            echo "   Created project: ID: {$testProject->id}, Name: {$testProject->name}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END DEBUG ===\n";
