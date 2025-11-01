<?php
/**
 * Test Idempotency & Actions
 * 
 * This script tests the idempotency of PATCH /tasks/{id}/move and PATCH /tasks/{id}/archive
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Api\App\TaskController;

// Mock Laravel environment
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing Idempotency & Actions\n";
echo "================================\n\n";

// Test 1: Archive Task Idempotency
echo "Test 1: Archive Task Idempotency\n";
echo "--------------------------------\n";

$controller = new TaskController();

// Mock request for archive
$request = new Request();
$request->merge(['reason' => 'Test archive']);

try {
    // First call
    echo "First archive call:\n";
    $response1 = $controller->archive($request, '1');
    $data1 = json_decode($response1->getContent(), true);
    echo "Status: " . ($data1['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Message: " . $data1['message'] . "\n";
    echo "Action: " . ($data1['data']['action'] ?? 'N/A') . "\n\n";
    
    // Second call (should be idempotent)
    echo "Second archive call (idempotency test):\n";
    $response2 = $controller->archive($request, '1');
    $data2 = json_decode($response2->getContent(), true);
    echo "Status: " . ($data2['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Message: " . $data2['message'] . "\n";
    echo "Action: " . ($data2['data']['action'] ?? 'N/A') . "\n\n";
    
    // Check idempotency
    if ($data2['data']['action'] === 'no_change') {
        echo "✅ IDEMPOTENCY TEST PASSED: Second call returned 'no_change'\n\n";
    } else {
        echo "❌ IDEMPOTENCY TEST FAILED: Second call did not return 'no_change'\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 2: Move Task Idempotency
echo "Test 2: Move Task Idempotency\n";
echo "-----------------------------\n";

// Mock request for move
$moveRequest = new Request();
$moveRequest->merge(['project_id' => '1', 'reason' => 'Test move']);

try {
    // First call
    echo "First move call:\n";
    $response1 = $controller->move($moveRequest, '1');
    $data1 = json_decode($response1->getContent(), true);
    echo "Status: " . ($data1['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Message: " . $data1['message'] . "\n";
    echo "Action: " . ($data1['data']['action'] ?? 'N/A') . "\n\n";
    
    // Second call (should be idempotent)
    echo "Second move call (idempotency test):\n";
    $response2 = $controller->move($moveRequest, '1');
    $data2 = json_decode($response2->getContent(), true);
    echo "Status: " . ($data2['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Message: " . $data2['message'] . "\n";
    echo "Action: " . ($data2['data']['action'] ?? 'N/A') . "\n\n";
    
    // Check idempotency
    if ($data2['data']['action'] === 'no_change') {
        echo "✅ IDEMPOTENCY TEST PASSED: Second call returned 'no_change'\n\n";
    } else {
        echo "❌ IDEMPOTENCY TEST FAILED: Second call did not return 'no_change'\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 3: Validation
echo "Test 3: Request Validation\n";
echo "-------------------------\n";

try {
    // Test invalid archive request
    echo "Testing invalid archive request (missing reason validation):\n";
    $invalidRequest = new Request();
    $invalidRequest->merge(['reason' => str_repeat('a', 501)]); // Too long
    
    $response = $controller->archive($invalidRequest, '1');
    $data = json_decode($response->getContent(), true);
    
    if (!$data['success'] && isset($data['errors'])) {
        echo "✅ VALIDATION TEST PASSED: Invalid request properly rejected\n";
        echo "Errors: " . json_encode($data['errors']) . "\n\n";
    } else {
        echo "❌ VALIDATION TEST FAILED: Invalid request was not rejected\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

echo "🏁 Idempotency & Actions Testing Complete\n";
