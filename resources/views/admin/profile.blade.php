<x-shared.layout-wrapper>
    <x-slot name="title">Admin Profile</x-slot>
    
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h1 class="text-2xl font-bold text-gray-900 mb-6">Admin Profile</h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Profile Information -->
                        <div class="space-y-4">
                            <h2 class="text-lg font-medium text-gray-900">Profile Information</h2>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ auth()->user()->name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ auth()->user()->email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Role</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ auth()->user()->role ?? 'admin' }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Information -->
                        <div class="space-y-4">
                            <h2 class="text-lg font-medium text-gray-900">System Information</h2>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Last Login</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ auth()->user()->last_login_at ? auth()->user()->last_login_at->format('M d, Y H:i') : 'Never' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Account Status</label>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ auth()->user()->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ auth()->user()->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-8 flex space-x-3">
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Edit Profile
                        </button>
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Change Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-shared.layout-wrapper>
