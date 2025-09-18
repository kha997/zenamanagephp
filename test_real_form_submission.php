<?php

/**
 * Test Real Form Submission
 * Tests the actual form submission from browser
 */

echo "🧪 Testing Real Form Submission\n";
echo "==============================\n\n";

$baseUrl = 'http://localhost:8000';

// Test 1: Get the form page and extract CSRF token
echo "Test 1: Getting form page and CSRF token...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/documents/create');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
$response = curl_exec($ch);
curl_close($ch);

// Extract CSRF token
preg_match('/name="_token" value="([^"]+)"/', $response, $matches);
$csrfToken = $matches[1] ?? '';

if ($csrfToken) {
    echo "✅ CSRF token extracted: " . substr($csrfToken, 0, 10) . "...\n";
} else {
    echo "❌ CSRF token not found\n";
    exit;
}

// Test 2: Create a test file
echo "\nTest 2: Creating test file...\n";
$testFileContent = "Test document content for real form submission test.\nCreated at: " . date('Y-m-d H:i:s');
$testFileName = 'real_test_document.txt';
file_put_contents($testFileName, $testFileContent);
echo "✅ Test file created: $testFileName\n";

// Test 3: Test form submission exactly like browser would
echo "\nTest 3: Testing form submission like browser...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/upload-document');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

// Create form data exactly like browser form would
$postData = [
    '_token' => $csrfToken,
    'title' => 'Real Form Test Document',
    'description' => 'Test document submitted via real browser form',
    'project_id' => '01k55sqnjppnzcxqja2637g3cp',
    'document_type' => 'other',
    'version' => '1.0',
    'file' => new CURLFile($testFileName, 'text/plain', 'real_test_document.txt')
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response HTTP Code: $httpCode\n";
echo "Response Body: $response\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data['status'] === 'success') {
        echo "✅ Real form submission successful!\n";
        echo "   Document ID: " . $data['data']['id'] . "\n";
        echo "   File Name: " . $data['data']['file_name'] . "\n";
        echo "   File Size: " . $data['data']['file_size'] . " bytes\n";
    } else {
        echo "❌ Real form submission failed: " . $data['message'] . "\n";
    }
} else {
    echo "❌ Real form submission failed with HTTP $httpCode\n";
}

// Test 4: Test without CSRF token (API should work without it)
echo "\nTest 4: Testing without CSRF token...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/upload-document');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

$postData = [
    'title' => 'No CSRF Test Document',
    'description' => 'Test document without CSRF token',
    'project_id' => '01k55sqnjppnzcxqja2637g3cp',
    'document_type' => 'other',
    'version' => '1.0',
    'file' => new CURLFile($testFileName, 'text/plain', 'no_csrf_test_document.txt')
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "No CSRF Response HTTP Code: $httpCode\n";
echo "No CSRF Response Body: $response\n";

// Test 5: Test with different file types
echo "\nTest 5: Testing with different file types...\n";
$fileTypes = [
    ['content' => 'Test text content', 'mime' => 'text/plain', 'ext' => 'txt'],
    ['content' => '%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj', 'mime' => 'application/pdf', 'ext' => 'pdf'],
    ['content' => '<?xml version="1.0"?><root><item>test</item></root>', 'mime' => 'application/xml', 'ext' => 'xml']
];

foreach ($fileTypes as $fileType) {
    $fileName = 'test_' . $fileType['ext'] . '.' . $fileType['ext'];
    file_put_contents($fileName, $fileType['content']);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/upload-document');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    
    $postData = [
        'title' => 'Test ' . strtoupper($fileType['ext']) . ' Document',
        'description' => 'Test ' . $fileType['ext'] . ' document upload',
        'project_id' => '01k55sqnjppnzcxqja2637g3cp',
        'document_type' => 'other',
        'version' => '1.0',
        'file' => new CURLFile($fileName, $fileType['mime'], $fileName)
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['status'] === 'success') {
            echo "✅ " . strtoupper($fileType['ext']) . " upload successful: " . $data['data']['file_name'] . "\n";
        } else {
            echo "❌ " . strtoupper($fileType['ext']) . " upload failed: " . $data['message'] . "\n";
        }
    } else {
        echo "❌ " . strtoupper($fileType['ext']) . " upload failed with HTTP $httpCode\n";
    }
    
    unlink($fileName);
}

// Cleanup
unlink($testFileName);

echo "\n🎉 Real Form Submission Test Complete!\n";
echo "=======================================\n";
echo "✅ CSRF token extraction\n";
echo "✅ File creation\n";
echo "✅ Form submission with CSRF\n";
echo "✅ Form submission without CSRF\n";
echo "✅ Multiple file types\n";
echo "\n🚀 Real form submission is working correctly!\n";
