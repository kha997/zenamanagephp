<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Test Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-6">Test Login</h1>
        
        <form id="testLoginForm">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" name="email" value="khafibo@gmail.com" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" name="password" value="Renzopi@@99" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" checked class="mr-2">
                    <span class="text-sm text-gray-700">Remember me</span>
                </label>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Test Login
            </button>
        </form>
        
        <div id="result" class="mt-6 p-4 bg-gray-50 rounded-md hidden">
            <h3 class="font-semibold mb-2">Result:</h3>
            <pre id="resultContent" class="text-sm"></pre>
        </div>
        
        <div class="mt-4">
            <a href="/test-session" target="_blank" class="text-blue-600 hover:text-blue-800">Check Session</a> |
            <a href="/app/dashboard" target="_blank" class="text-blue-600 hover:text-blue-800">Dashboard</a>
        </div>
    </div>

    <script>
        document.getElementById('testLoginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            const resultContent = document.getElementById('resultContent');
            
            try {
                console.log('Sending login request...');
                
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Web-Login': 'true'
                    },
                    body: JSON.stringify({
                        email: formData.get('email'),
                        password: formData.get('password'),
                        remember: formData.get('remember') === 'on'
                    })
                });
                
                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);
                
                resultContent.textContent = JSON.stringify(data, null, 2);
                resultDiv.classList.remove('hidden');
                
                if (data.success) {
                    console.log('Login successful, redirecting...');
                    setTimeout(() => {
                        window.location.href = '/app/dashboard';
                    }, 2000);
                }
                
            } catch (error) {
                console.error('Login error:', error);
                resultContent.textContent = 'Error: ' + error.message;
                resultDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/test-login.blade.php ENDPATH**/ ?>