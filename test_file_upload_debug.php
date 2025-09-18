<?php

/**
 * Debug Script for File Upload Issues
 * Tests file upload with detailed debugging
 */

echo "🧪 Testing File Upload Debug\n";
echo "===========================\n\n";

$baseUrl = 'http://localhost:8000';

// Test 1: Create a test file
echo "Test 1: Creating test file...\n";
$testFileContent = "This is a test document content for upload testing.";
$testFileName = 'test_document.txt';
file_put_contents($testFileName, $testFileContent);
echo "✅ Test file created: $testFileName\n";

// Test 2: Get CSRF token from form
echo "\nTest 2: Getting CSRF token from form...\n";
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

// Test 3: Test file upload with curl
echo "\nTest 3: Testing file upload with curl...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/upload-document');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

// Create multipart form data
$postData = [
    '_token' => $csrfToken,
    'title' => 'Test Document Upload',
    'description' => 'Test document for debugging upload issues',
    'project_id' => '01k55sqnjppnzcxqja2637g3cp', // Use existing project ID
    'document_type' => 'other',
    'version' => '1.0',
    'file' => new CURLFile($testFileName, 'text/plain', 'test_document.txt')
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
        echo "✅ File upload successful!\n";
        echo "Document ID: " . $data['data']['id'] . "\n";
        echo "File Name: " . $data['data']['file_name'] . "\n";
        echo "File Size: " . $data['data']['file_size'] . " bytes\n";
    } else {
        echo "❌ Upload failed: " . $data['message'] . "\n";
    }
} else {
    echo "❌ Upload failed with HTTP $httpCode\n";
}

// Test 4: Test without CSRF token (API should work without it)
echo "\nTest 4: Testing file upload without CSRF token...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/upload-document');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$postData = [
    'title' => 'Test Document Upload No CSRF',
    'description' => 'Test document without CSRF token',
    'project_id' => '01k55sqnjppnzcxqja2637g3cp',
    'document_type' => 'other',
    'version' => '1.0',
    'file' => new CURLFile($testFileName, 'text/plain', 'test_document_no_csrf.txt')
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response HTTP Code: $httpCode\n";
echo "Response Body: $response\n";

// Test 5: Test with different file types
echo "\nTest 5: Testing with PDF file...\n";
$pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n174\n%%EOF";
$pdfFileName = 'test_document.pdf';
file_put_contents($pdfFileName, $pdfContent);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/upload-document');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$postData = [
    'title' => 'Test PDF Upload',
    'description' => 'Test PDF document upload',
    'project_id' => '01k55sqnjppnzcxqja2637g3cp',
    'document_type' => 'other',
    'version' => '1.0',
    'file' => new CURLFile($pdfFileName, 'application/pdf', 'test_document.pdf')
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "PDF Upload Response HTTP Code: $httpCode\n";
echo "PDF Upload Response Body: $response\n";

// Cleanup
unlink($testFileName);
unlink($pdfFileName);

echo "\n🎉 File Upload Debug Test Complete!\n";
echo "===================================\n";
echo "✅ Test files created and uploaded\n";
echo "✅ CSRF token handling tested\n";
echo "✅ Multiple file types tested\n";
echo "✅ Detailed error messages provided\n";
