<!-- Force MFA Modal -->
<div x-show="showForceMfaModal" 
     x-transition:enter="ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto" 
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true"
     style="display: none;">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
             @click="showForceMfaModal = false"
             aria-hidden="true"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-user-shield text-blue-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Force MFA for Users
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                This will require the selected users to set up multi-factor authentication on their next login.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Selected Users List -->
                <div class="mt-4" x-show="selectedItems.length > 0">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Selected Users (<span x-text="selectedItems.length"></span>)
                    </label>
                    <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-md p-2 bg-gray-50">
                        <template x-for="userId in selectedItems" :key="userId">
                            <div class="text-sm text-gray-700 py-1" x-text="getUserName(userId)"></div>
                        </template>
                    </div>
                </div>
                
                <!-- Manual User Selection -->
                <div class="mt-4" x-show="selectedItems.length === 0">
                    <label for="user-search" class="block text-sm font-medium text-gray-700 mb-2">
                        Search and select users
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="user-search"
                            x-model="userSearchQuery"
                            @input.debounce.250ms="searchUsers()"
                            placeholder="Search by name or email..."
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Search Results -->
                    <div x-show="userSearchResults.length > 0" class="mt-2 max-h-32 overflow-y-auto border border-gray-200 rounded-md">
                        <template x-for="user in userSearchResults" :key="user.id">
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50">
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        :value="user.id" 
                                        x-model="manualSelectedUsers"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900" x-text="user.name"></div>
                                        <div class="text-sm text-gray-500" x-text="user.email"></div>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500" x-text="user.tenantName"></span>
                            </div>
                        </template>
                    </div>
                </div>
                
                <!-- Options -->
                <div class="mt-4">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="send-notification"
                            x-model="sendMfaNotification"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        >
                        <label for="send-notification" class="ml-2 text-sm text-gray-700">
                            Send email notification to users
                        </label>
                    </div>
                </div>
                
                <!-- Warning -->
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-0.5"></i>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-800">
                                Users will be required to set up MFA on their next login. They will not be able to access the system until MFA is configured.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    @click="confirmForceMfa()"
                    :disabled="getTotalSelectedUsers() === 0"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-user-shield mr-2"></i>
                    Force MFA (<span x-text="getTotalSelectedUsers()"></span>)
                </button>
                <button 
                    @click="showForceMfaModal = false"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Force MFA modal methods are now in the main securityPage component --}}
