<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center gap-4">
                        <a href="/dashboard" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Calendar</h1>
                            <p class="text-gray-600 mt-1">Schedule and manage your work calendar</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Event
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Calendar Placeholder -->
            <div class="bg-white rounded-lg shadow p-8">
                <div class="text-center">
                    <i class="fas fa-calendar-alt text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Calendar View</h3>
                    <p class="text-gray-600 mb-6">Your calendar will be displayed here with all scheduled events and tasks.</p>
                    <div class="grid grid-cols-7 gap-2 mb-4">
                        <div class="p-2 text-center font-medium text-gray-700">Sun</div>
                        <div class="p-2 text-center font-medium text-gray-700">Mon</div>
                        <div class="p-2 text-center font-medium text-gray-700">Tue</div>
                        <div class="p-2 text-center font-medium text-gray-700">Wed</div>
                        <div class="p-2 text-center font-medium text-gray-700">Thu</div>
                        <div class="p-2 text-center font-medium text-gray-700">Fri</div>
                        <div class="p-2 text-center font-medium text-gray-700">Sat</div>
                    </div>
                    <div class="grid grid-cols-7 gap-2">
                        <!-- Calendar days would be populated here -->
                        <div class="h-20 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500">1</div>
                        <div class="h-20 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500">2</div>
                        <div class="h-20 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500">3</div>
                        <div class="h-20 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500">4</div>
                        <div class="h-20 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500">5</div>
                        <div class="h-20 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500">6</div>
                        <div class="h-20 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500">7</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/calendar/index.blade.php ENDPATH**/ ?>