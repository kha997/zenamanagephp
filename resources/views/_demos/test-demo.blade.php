<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Test - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white rounded-lg shadow p-8 max-w-md w-full">
            <h1 class="text-2xl font-bold text-center mb-6">Demo Test</h1>
            
            <div class="space-y-4">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-semibold text-blue-800">User Info</h3>
                    <p class="text-sm text-blue-600">Name: {{ auth()->user()->name ?? 'Not authenticated' }}</p>
                    <p class="text-sm text-blue-600">Email: {{ auth()->user()->email ?? 'N/A' }}</p>
                    <p class="text-sm text-blue-600">Role: {{ auth()->user()->role ?? 'N/A' }}</p>
                </div>
                
                <div class="p-4 bg-green-50 rounded-lg">
                    <h3 class="font-semibold text-green-800">Tenant Info</h3>
                    <p class="text-sm text-green-600">Tenant: {{ auth()->user()->tenant->name ?? 'No tenant' }}</p>
                    <p class="text-sm text-green-600">Slug: {{ auth()->user()->tenant->slug ?? 'N/A' }}</p>
                </div>
                
                <div class="p-4 bg-purple-50 rounded-lg">
                    <h3 class="font-semibold text-purple-800">Status</h3>
                    <p class="text-sm text-purple-600">✅ Demo middleware working</p>
                    <p class="text-sm text-purple-600">✅ User authenticated</p>
                    <p class="text-sm text-purple-600">✅ Tenant loaded</p>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <a href="/demo/header" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Test Header Demo
                </a>
            </div>
        </div>
    </div>
</body>
</html>
