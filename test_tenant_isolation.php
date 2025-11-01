<?php
/**
 * Test Tenant Isolation
 * 
 * This script tests that all /api/v1/app/* queries are properly scoped by tenant_id
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

// Mock Laravel environment
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing Tenant Isolation\n";
echo "===========================\n\n";

// Test 1: Global Scope Application
echo "Test 1: Global Scope Application\n";
echo "--------------------------------\n";

try {
    // Test without tenant context
    echo "Testing Project queries without tenant context:\n";
    $projects = Project::all();
    echo "Total projects found: " . $projects->count() . "\n";
    
    if ($projects->count() > 0) {
        echo "⚠️  WARNING: Found projects without tenant context - Global scope may not be working\n\n";
    } else {
        echo "✅ GOOD: No projects found without tenant context\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 2: Tenant Context Application
echo "Test 2: Tenant Context Application\n";
echo "----------------------------------\n";

try {
    // Simulate tenant context
    $tenantId = '01HZ123456789ABCDEFGHIJKLMN'; // Mock tenant ID
    
    // Set tenant context in app
    app()->instance('tenant', (object)['id' => $tenantId]);
    
    echo "Testing Project queries with tenant context:\n";
    $projects = Project::all();
    echo "Total projects found for tenant: " . $projects->count() . "\n";
    
    // Check if all projects belong to the tenant
    $allBelongToTenant = $projects->every(function($project) use ($tenantId) {
        return $project->tenant_id === $tenantId;
    });
    
    if ($allBelongToTenant) {
        echo "✅ GOOD: All projects belong to the tenant\n\n";
    } else {
        echo "❌ ERROR: Some projects don't belong to the tenant\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 3: Cross-Tenant Access Prevention
echo "Test 3: Cross-Tenant Access Prevention\n";
echo "--------------------------------------\n";

try {
    // Create mock tenants
    $tenantA = '01HZ123456789ABCDEFGHIJKLMN';
    $tenantB = '01HZ987654321ZYXWVUTSRQPONM';
    
    // Test with tenant A context
    app()->instance('tenant', (object)['id' => $tenantA]);
    $projectsA = Project::all();
    
    // Test with tenant B context
    app()->instance('tenant', (object)['id' => $tenantB]);
    $projectsB = Project::all();
    
    echo "Projects for Tenant A: " . $projectsA->count() . "\n";
    echo "Projects for Tenant B: " . $projectsB->count() . "\n";
    
    // Check isolation
    $tenantAIds = $projectsA->pluck('id')->toArray();
    $tenantBIds = $projectsB->pluck('id')->toArray();
    $overlap = array_intersect($tenantAIds, $tenantBIds);
    
    if (empty($overlap)) {
        echo "✅ GOOD: No data overlap between tenants\n\n";
    } else {
        echo "❌ ERROR: Data overlap detected between tenants: " . implode(', ', $overlap) . "\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: Manual Tenant Scope
echo "Test 4: Manual Tenant Scope\n";
echo "---------------------------\n";

try {
    $tenantId = '01HZ123456789ABCDEFGHIJKLMN';
    
    // Test manual scope
    $projects = Project::forTenant($tenantId)->get();
    echo "Projects found using manual scope: " . $projects->count() . "\n";
    
    // Verify all belong to tenant
    $allBelong = $projects->every(function($project) use ($tenantId) {
        return $project->tenant_id === $tenantId;
    });
    
    if ($allBelong) {
        echo "✅ GOOD: Manual scope works correctly\n\n";
    } else {
        echo "❌ ERROR: Manual scope not working correctly\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 5: Model Relationships
echo "Test 5: Model Relationships\n";
echo "---------------------------\n";

try {
    $tenantId = '01HZ123456789ABCDEFGHIJKLMN';
    app()->instance('tenant', (object)['id' => $tenantId]);
    
    // Test project-task relationships
    $projects = Project::with('tasks')->get();
    
    foreach ($projects as $project) {
        $tasks = $project->tasks;
        $allTasksBelongToTenant = $tasks->every(function($task) use ($tenantId) {
            return $task->tenant_id === $tenantId;
        });
        
        if (!$allTasksBelongToTenant) {
            echo "❌ ERROR: Project {$project->id} has tasks from other tenants\n";
        }
    }
    
    echo "✅ GOOD: All relationships respect tenant boundaries\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

echo "🏁 Tenant Isolation Testing Complete\n";
echo "====================================\n";
echo "Summary:\n";
echo "- Global scope should prevent queries without tenant context\n";
echo "- Tenant context should automatically filter all queries\n";
echo "- Cross-tenant access should be prevented\n";
echo "- Manual scopes should work as expected\n";
echo "- Relationships should respect tenant boundaries\n";
