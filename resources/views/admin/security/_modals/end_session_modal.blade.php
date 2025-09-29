<!-- End Session Modal -->
<div x-show="showEndSessionModal" 
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
             @click="showEndSessionModal = false"
             aria-hidden="true"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-times text-orange-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            End User Session
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                This will immediately terminate the selected user session.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Session Information -->
                <div class="mt-4" x-show="selectedSession">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Session Information</h4>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">User</dt>
                                <dd class="text-sm text-gray-900" x-text="selectedSession?.user || 'N/A'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Device</dt>
                                <dd class="text-sm text-gray-900" x-text="selectedSession?.device || 'Unknown'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                                <dd class="text-sm text-gray-900 font-mono" x-text="selectedSession?.ip || 'N/A'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Location</dt>
                                <dd class="text-sm text-gray-900" x-text="selectedSession?.location || 'Unknown'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Seen</dt>
                                <dd class="text-sm text-gray-900" x-text="formatDateTime(selectedSession?.lastSeenAt)"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                <dd class="text-sm text-gray-900" x-text="formatDuration(selectedSession?.durationSec)"></dd>
                            </div>
                        </dl>
                    </div>
                </div>
                
                <!-- End Session Reason -->
                <div class="mt-4">
                    <label for="end-session-reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Reason for ending session
                    </label>
                    <select 
                        id="end-session-reason"
                        x-model="endSessionReason"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    >
                        <option value="">Select a reason...</option>
                        <option value="security_concern">Security concern</option>
                        <option value="suspicious_activity">Suspicious activity</option>
                        <option value="policy_violation">Policy violation</option>
                        <option value="user_request">User requested logout</option>
                        <option value="admin_action">Administrator action</option>
                        <option value="inactive_session">Inactive session cleanup</option>
                        <option value="other">Other (specify below)</option>
                    </select>
                </div>
                
                <!-- Custom Reason -->
                <div class="mt-4" x-show="endSessionReason === 'other'">
                    <label for="custom-end-session-reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Custom reason
                    </label>
                    <textarea 
                        id="custom-end-session-reason"
                        x-model="customEndSessionReason"
                        rows="3"
                        placeholder="Please provide details..."
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    ></textarea>
                </div>
                
                <!-- Impact Warning -->
                <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-md">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-orange-400 mt-0.5"></i>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-orange-800">Impact Warning</h4>
                            <p class="text-sm text-orange-700 mt-1">
                                Ending this session will immediately log out the user. Any unsaved work may be lost.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmation -->
                <div class="mt-4">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="confirm-end-session"
                            x-model="confirmEndSession"
                            class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                        >
                        <label for="confirm-end-session" class="ml-2 text-sm text-gray-700">
                            I understand this will immediately log out the user
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    @click="confirmEndSession()"
                    :disabled="!endSessionReason || !confirmEndSession"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-orange-600 text-base font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-times mr-2"></i>
                    End Session
                </button>
                <button 
                    @click="showEndSessionModal = false"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

{{-- End Session modal methods are now in the main securityPage component --}}
