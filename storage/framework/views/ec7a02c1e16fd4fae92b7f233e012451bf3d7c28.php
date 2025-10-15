<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Dashboard - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- SECURITY WARNING BANNER -->
    <div class="bg-red-600 text-white px-4 py-2 text-center text-sm font-semibold">
        🚨 AUTH DISABLED (DEV ONLY) - Dashboard routes moved to /_debug namespace for security
    </div>

    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">ZenaManage Debug Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Welcome, <?php echo e($user->name); ?></span>
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"><?php echo e($user->role); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- KPI Cards -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-project-diagram text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Projects</p>
                        <p class="text-2xl font-semibold text-gray-900">12</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-tasks text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Completed Tasks</p>
                        <p class="text-2xl font-semibold text-gray-900">48</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Tasks</p>
                        <p class="text-2xl font-semibold text-gray-900">23</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Team Members</p>
                        <p class="text-2xl font-semibold text-gray-900">8</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Debug Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="font-medium text-gray-700">Environment</h3>
                    <p class="text-sm text-gray-600"><?php echo e(app()->environment()); ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-700">Debug Mode</h3>
                    <p class="text-sm text-gray-600"><?php echo e(config('app.debug') ? 'Enabled' : 'Disabled'); ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-700">User ID</h3>
                    <p class="text-sm text-gray-600"><?php echo e($user->id); ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-700">User Role</h3>
                    <p class="text-sm text-gray-600"><?php echo e($user->role); ?></p>
                </div>
            </div>
        </div>

        <!-- Status Information -->
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Authentication Status</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Auth middleware is temporarily disabled due to technical issues.</p>
                        <p class="mt-1">This dashboard is only accessible in local environment with DebugGate protection.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/debug/simple-dashboard.blade.php ENDPATH**/ ?>