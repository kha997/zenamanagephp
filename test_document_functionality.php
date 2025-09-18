<?php

/**
 * Test Document Functionality
 * Tests all document-related endpoints and features
 */

echo "🧪 Testing Document Functionality\n";
echo "================================\n\n";

$baseUrl = 'http://localhost:8000';

// Test endpoints
$endpoints = [
    'GET /api/v1/documents' => 'List documents',
    'GET /api/v1/documents-simple' => 'List documents (simple)',
    'POST /api/v1/documents' => 'Upload document',
    'GET /api/v1/test' => 'Test endpoint',
];

$results = [];

foreach ($endpoints as $endpoint => $description) {
    echo "Testing: $description ($endpoint)\n";
    
    $url = $baseUrl . str_replace('GET ', '', str_replace('POST ', '', $endpoint));
    $method = strpos($endpoint, 'POST') === 0 ? 'POST' : 'GET';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        
        // Create a test file
        $testFile = tempnam(sys_get_temp_dir(), 'test_doc');
        file_put_contents($testFile, 'This is a test document content.');
        
        $postData = [
            'title' => 'Test Document ' . date('Y-m-d H:i:s'),
            'description' => 'Test document description',
            'file' => new CURLFile($testFile, 'text/plain', 'test_document.txt')
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $results[$endpoint] = "❌ Error: $error";
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $json = json_decode($response, true);
        if ($json !== null) {
            $results[$endpoint] = "✅ Success (HTTP $httpCode) - JSON response";
        } else {
            $results[$endpoint] = "✅ Success (HTTP $httpCode) - Non-JSON response";
        }
    } else {
        $results[$endpoint] = "❌ Failed (HTTP $httpCode)";
    }
    
    echo "Result: " . $results[$endpoint] . "\n\n";
    
    // Clean up test file
    if ($method === 'POST' && isset($testFile)) {
        unlink($testFile);
    }
}

// Test web routes
echo "Testing Web Routes:\n";
echo "==================\n\n";

$webRoutes = [
    '/documents' => 'Documents index page',
    '/documents/create' => 'Document upload page',
    '/documents/approvals' => 'Document approvals page',
];

foreach ($webRoutes as $route => $description) {
    echo "Testing: $description ($route)\n";
    
    $url = $baseUrl . $route;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $results[$route] = "❌ Error: $error";
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $results[$route] = "✅ Success (HTTP $httpCode)";
    } else {
        $results[$route] = "❌ Failed (HTTP $httpCode)";
    }
    
    echo "Result: " . $results[$route] . "\n\n";
}

// Summary
echo "📊 Test Summary:\n";
echo "================\n\n";

$successCount = 0;
$totalCount = count($results);

foreach ($results as $endpoint => $result) {
    echo "$endpoint: $result\n";
    if (strpos($result, '✅') === 0) {
        $successCount++;
    }
}

echo "\n";
echo "Total Tests: $totalCount\n";
echo "Successful: $successCount\n";
echo "Failed: " . ($totalCount - $successCount) . "\n";
echo "Success Rate: " . round(($successCount / $totalCount) * 100, 2) . "%\n\n";

if ($successCount === $totalCount) {
    echo "🎉 All document functionality tests passed!\n";
} else {
    echo "⚠️  Some tests failed. Please check the results above.\n";
}

echo "\n";
echo "🔗 Test URLs:\n";
echo "=============\n";
echo "API Endpoints:\n";
echo "- List Documents: $baseUrl/api/v1/documents\n";
echo "- Simple Documents: $baseUrl/api/v1/documents-simple\n";
echo "- Test Endpoint: $baseUrl/api/v1/test\n";
echo "\n";
echo "Web Pages:\n";
echo "- Documents Index: $baseUrl/documents\n";
echo "- Upload Document: $baseUrl/documents/create\n";
echo "- Document Approvals: $baseUrl/documents/approvals\n";
echo "\n";
echo "Dashboard: $baseUrl/dashboard\n";

?>
