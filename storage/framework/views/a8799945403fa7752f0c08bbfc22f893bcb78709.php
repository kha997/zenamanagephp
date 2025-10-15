
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tailwind CSS Test - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#10b981',
                        accent: '#8b5cf6'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen">
    <!-- Test Header -->
    <header class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-3 rounded-xl">
                        <i class="fas fa-rocket text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Tailwind CSS Test</h1>
                        <p class="text-gray-600">Testing Tailwind CSS functionality</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                        <i class="fas fa-check-circle mr-2"></i>
                        Working
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- Test Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Test Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-chart-bar text-blue-600 text-xl"></i>
                    </div>
                    <span class="text-2xl font-bold text-blue-600">95%</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Performance</h3>
                <p class="text-gray-600 text-sm">Excellent performance metrics</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <span class="text-2xl font-bold text-green-600">1,247</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Users</h3>
                <p class="text-gray-600 text-sm">Active users count</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-project-diagram text-purple-600 text-xl"></i>
                    </div>
                    <span class="text-2xl font-bold text-purple-600">89</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Projects</h3>
                <p class="text-gray-600 text-sm">Active projects</p>
            </div>
        </div>

        <!-- Test Buttons -->
        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Interactive Elements</h2>
            <div class="flex flex-wrap gap-4">
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Primary Button
                </button>
                <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-check mr-2"></i>
                    Success Button
                </button>
                <button class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-magic mr-2"></i>
                    Accent Button
                </button>
                <button class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-times mr-2"></i>
                    Secondary Button
                </button>
            </div>
        </div>

        <!-- Test Forms -->
        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Form Elements</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                    <input type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter your name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter your email">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea rows="4" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter your message"></textarea>
                </div>
            </div>
        </div>

        <!-- Test Alerts -->
        <div class="space-y-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-3"></i>
                    <div>
                        <h3 class="text-sm font-medium text-green-800">Success!</h3>
                        <p class="text-sm text-green-700">Tailwind CSS is working perfectly.</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                    <div>
                        <h3 class="text-sm font-medium text-blue-800">Information</h3>
                        <p class="text-sm text-blue-700">This is a test page to verify Tailwind CSS functionality.</p>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                    <div>
                        <h3 class="text-sm font-medium text-yellow-800">Warning</h3>
                        <p class="text-sm text-yellow-700">Make sure to test all components thoroughly.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Test Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h3 class="text-lg font-semibold mb-2">ZenaManage</h3>
                <p class="text-gray-400">Tailwind CSS Test Page</p>
            </div>
        </div>
    </footer>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/test-tailwind.blade.php ENDPATH**/ ?>