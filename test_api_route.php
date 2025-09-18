<?php

// Test API endpoint directly
$url = 'http://localhost:8000/api/tasks';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json'
    ]
]);

echo "=== API ENDPOINT TEST ===\n\n";

echo "1. Testing API endpoint: {$url}\n";

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "Error: Failed to get response from API\n";
} else {
    echo "2. API Response:\n";
    echo $response . "\n";
    
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "3. API Success: YES\n";
        echo "   - Tasks count: " . count($data['data']['tasks']) . "\n";
        echo "   - Total: " . $data['data']['total'] . "\n";
        
        foreach ($data['data']['tasks'] as $task) {
            echo "   - ID: {$task['id']}, Name: {$task['name']}, Status: {$task['status']}\n";
        }
    } else {
        echo "3. API Success: NO\n";
        echo "   - Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
}

echo "\n=== END TEST ===\n";
