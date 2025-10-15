<!DOCTYPE html>
<html>
<head>
    <title>Test Simple View</title>
</head>
<body>
    <h1>Test Simple View</h1>
    <p>This is a simple test view.</p>
    <p>Clients count: {{ $clients->count() }}</p>
    <p>Stats: {{ json_encode($stats) }}</p>
</body>
</html>
