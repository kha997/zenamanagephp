<!-- Block IP Modal -->
<div x-show="showBlockIpModal" 
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
             @click="showBlockIpModal = false"
             aria-hidden="true"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-ban text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Block IP Address
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Add an IP address or CIDR block to the denylist to prevent access.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- IP Address Input -->
                <div class="mt-4">
                    <label for="ip-address" class="block text-sm font-medium text-gray-700 mb-2">
                        IP Address or CIDR Block
                    </label>
                    <input 
                        type="text" 
                        id="ip-address"
                        x-model="ipAddress"
                        placeholder="192.168.1.1 or 192.168.1.0/24"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Enter a single IP address (e.g., 192.168.1.1) or a CIDR block (e.g., 192.168.1.0/24)
                    </p>
                </div>
                
                <!-- Blocking Reason -->
                <div class="mt-4">
                    <label for="blocking-reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Reason for blocking
                    </label>
                    <select 
                        id="blocking-reason"
                        x-model="blockingReason"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    >
                        <option value="">Select a reason...</option>
                        <option value="brute_force">Brute force attack</option>
                        <option value="suspicious_activity">Suspicious activity</option>
                        <option value="malware_source">Known malware source</option>
                        <option value="spam_source">Spam source</option>
                        <option value="policy_violation">Policy violation</option>
                        <option value="admin_request">Administrator request</option>
                        <option value="other">Other (specify below)</option>
                    </select>
                </div>
                
                <!-- Custom Reason -->
                <div class="mt-4" x-show="blockingReason === 'other'">
                    <label for="custom-blocking-reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Custom reason
                    </label>
                    <textarea 
                        id="custom-blocking-reason"
                        x-model="customBlockingReason"
                        rows="3"
                        placeholder="Please provide details..."
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    ></textarea>
                </div>
                
                <!-- Blocking Duration -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Blocking Duration
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input 
                                type="radio" 
                                id="permanent"
                                name="blocking-duration"
                                value="permanent"
                                x-model="blockingDuration"
                                class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                            >
                            <label for="permanent" class="ml-2 text-sm text-gray-700">
                                Permanent (until manually removed)
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input 
                                type="radio" 
                                id="temporary"
                                name="blocking-duration"
                                value="temporary"
                                x-model="blockingDuration"
                                class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                            >
                            <label for="temporary" class="ml-2 text-sm text-gray-700">
                                Temporary
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Temporary Duration -->
                <div class="mt-4" x-show="blockingDuration === 'temporary'">
                    <label for="temporary-duration" class="block text-sm font-medium text-gray-700 mb-2">
                        Block for
                    </label>
                    <div class="flex space-x-2">
                        <input 
                            type="number" 
                            id="temporary-duration"
                            x-model="temporaryDuration"
                            min="1"
                            max="365"
                            class="block w-20 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        >
                        <select 
                            x-model="temporaryDurationUnit"
                            class="block w-24 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        >
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                            <option value="weeks">Weeks</option>
                        </select>
                    </div>
                </div>
                
                <!-- Impact Warning -->
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-0.5"></i>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-yellow-800">Impact Warning</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                Blocking this IP will prevent all access from this address. Make sure this is the intended action.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmation -->
                <div class="mt-4">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="confirm-blocking"
                            x-model="confirmBlocking"
                            class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                        >
                        <label for="confirm-blocking" class="ml-2 text-sm text-gray-700">
                            I confirm that blocking this IP address is necessary and appropriate
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    @click="confirmBlockIp()"
                    :disabled="!ipAddress || !blockingReason || !confirmBlocking"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-ban mr-2"></i>
                    Block IP
                </button>
                <button 
                    @click="showBlockIpModal = false"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Block IP modal methods are now in the main securityPage component --}}
