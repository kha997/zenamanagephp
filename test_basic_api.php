<?php declare(strict_types=1);

echo "🧪 Testing basic API endpoint (no auth required)...\n";

$url = 'http://localhost/zenamanage/public/api/v1/test';
$response = file_get_contents($url);

if ($response !== false) {
    echo "✅ Basic API works!\n";
    echo "📄 Response: $response\n";
} else {
    echo "❌ Basic API failed\n";
}