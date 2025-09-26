<!-- App Settings Content -->
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-6">User Settings</h3>
    
    <div class="space-y-6">
        <div class="border-b pb-4">
            <h4 class="font-medium text-gray-900 mb-2">Profile Information</h4>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" value="John Doe" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" value="john.doe@example.com" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="tel" value="+1 (555) 123-4567" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
            </div>
        </div>
        
        <div class="border-b pb-4">
            <h4 class="font-medium text-gray-900 mb-2">Preferences</h4>
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" class="rounded" checked>
                    <span class="ml-2 text-sm text-gray-700">Email notifications</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" class="rounded" checked>
                    <span class="ml-2 text-sm text-gray-700">Desktop notifications</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" class="rounded">
                    <span class="ml-2 text-sm text-gray-700">Dark mode</span>
                </label>
            </div>
        </div>
        
        <div class="flex justify-end">
            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Save Changes
            </button>
        </div>
    </div>
</div>