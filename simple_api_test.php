<?php declare(strict_types=1);

echo "🧪 Testing API endpoint /api/v1/user...\n";

$testUrl = 'http://localhost/zenamanage/api/v1/user';
$headers = [
    'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0L3plbmFtYW5hZ2UvYXBpL3YxL2F1dGgvbG9naW4iLCJpYXQiOjE3NTY2NTU4NzQsImV4cCI6MTc1NjY1OTQ3NCwibmJmIjoxNzU2NjU1ODc0LCJqdGkiOiJhVGNOZGNGVGNqVGNOZGNGIiwic3ViIjoiMSIsInBydiI6IjIzYmQ1Yzg5NDlmNjAwYWRiMzllNzAxYzQwMDg3MmRiN2E1OTc2ZjcifQ.abc123',
    'Content-Type: application/json',
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: $error\n";
} else {
    echo "📡 HTTP Status: $httpCode\n";
    echo "📄 Response: $response\n";
    
    if ($httpCode === 200) {
        echo "\n✅ API test thành công!\n";
    } else {
        echo "\n⚠️  API vẫn có vấn đề. HTTP $httpCode\n";
    }
}