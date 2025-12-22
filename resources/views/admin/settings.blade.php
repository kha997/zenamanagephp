<x-shared.layout-wrapper>
    <x-slot name="title">Admin Settings</x-slot>
    
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h1 class="text-2xl font-bold text-gray-900 mb-6">Admin Settings</h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- System Settings -->
                        <div class="space-y-4">
                            <h2 class="text-lg font-medium text-gray-900">System Settings</h2>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Site Name</label>
                                    <input type="text" value="ZenaManage" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Maintenance Mode</label>
                                    <div class="mt-1">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" class="form-checkbox">
                                            <span class="ml-2 text-sm text-gray-700">Enable maintenance mode</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Settings -->
                        <div class="space-y-4">
                            <h2 class="text-lg font-medium text-gray-900">Security Settings</h2>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Session Timeout</label>
                                    <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option>30 minutes</option>
                                        <option>1 hour</option>
                                        <option>2 hours</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Password Policy</label>
                                    <div class="mt-1 space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" checked class="form-checkbox">
                                            <span class="ml-2 text-sm text-gray-700">Require strong passwords</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" checked class="form-checkbox">
                                            <span class="ml-2 text-sm text-gray-700">Enable 2FA</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-8 flex space-x-3">
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Settings
                        </button>
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Reset to Defaults
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-shared.layout-wrapper>
