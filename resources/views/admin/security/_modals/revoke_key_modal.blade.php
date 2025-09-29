<!-- Revoke Key Modal -->
<div x-show="showRevokeKeyModal" 
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
             @click="showRevokeKeyModal = false"
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
                            Revoke API Key
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                This action will permanently revoke the selected API key. This cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Key Information -->
                <div class="mt-4" x-show="selectedKey">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Key Information</h4>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Key ID</dt>
                                <dd class="text-sm text-gray-900 font-mono" x-text="selectedKey?.id ? maskKeyId(selectedKey.id) : 'N/A'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Owner</dt>
                                <dd class="text-sm text-gray-900" x-text="selectedKey?.owner || 'N/A'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Scope</dt>
                                <dd class="text-sm text-gray-900" x-text="selectedKey?.scope ? selectedKey.scope.join(', ') : 'N/A'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Used</dt>
                                <dd class="text-sm text-gray-900" x-text="formatDate(selectedKey?.lastUsedAt)"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created</dt>
                                <dd class="text-sm text-gray-900" x-text="formatDate(selectedKey?.createdAt)"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Risk Level</dt>
                                <dd class="text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                          :class="getRiskBadgeClass(selectedKey?.risk)" x-text="selectedKey?.risk || 'N/A'"></span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                
                <!-- Revocation Reason -->
                <div class="mt-4">
                    <label for="revocation-reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Reason for revocation
                    </label>
                    <select 
                        id="revocation-reason"
                        x-model="revocationReason"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    >
                        <option value="">Select a reason...</option>
                        <option value="security_breach">Security breach suspected</option>
                        <option value="key_compromised">Key may be compromised</option>
                        <option value="user_request">User requested revocation</option>
                        <option value="policy_violation">Policy violation</option>
                        <option value="inactive_key">Inactive key cleanup</option>
                        <option value="other">Other (specify below)</option>
                    </select>
                </div>
                
                <!-- Custom Reason -->
                <div class="mt-4" x-show="revocationReason === 'other'">
                    <label for="custom-reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Custom reason
                    </label>
                    <textarea 
                        id="custom-reason"
                        x-model="customRevocationReason"
                        rows="3"
                        placeholder="Please provide details..."
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    ></textarea>
                </div>
                
                <!-- Impact Warning -->
                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-red-400 mt-0.5"></i>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-red-800">Impact Warning</h4>
                            <p class="text-sm text-red-700 mt-1">
                                Revoking this API key will immediately break any applications or services using it. 
                                Make sure to update all integrations before proceeding.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmation -->
                <div class="mt-4">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="confirm-revocation"
                            x-model="confirmRevocation"
                            class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                        >
                        <label for="confirm-revocation" class="ml-2 text-sm text-gray-700">
                            I understand this action cannot be undone and will break existing integrations
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    @click="confirmRevokeKey()"
                    :disabled="!revocationReason || !confirmRevocation"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-ban mr-2"></i>
                    Revoke Key
                </button>
                <button 
                    @click="showRevokeKeyModal = false"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Revoke Key modal methods are now in the main securityPage component --}}
