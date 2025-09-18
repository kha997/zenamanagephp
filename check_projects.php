<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Project;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROJECTS CHECK ===\n\n";

try {
    // Get all projects
    $projects = Project::all();
    
    echo "Available projects:\n";
    foreach ($projects as $project) {
        echo "- ID: {$project->id}\n";
        echo "  Name: {$project->name}\n";
        echo "  Status: {$project->status}\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== END CHECK ===\n";
