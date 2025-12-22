<!-- Edit Policy Modal -->
<div x-show="showEditPolicyModal" 
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
             @click="showEditPolicyModal = false"
             aria-hidden="true"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-cog text-blue-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Edit Security Policy
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Configure password and session security policies for all tenants.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Policy Type Selection -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Policy Type
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input 
                                type="radio" 
                                id="password-policy"
                                name="policy-type"
                                value="password"
                                x-model="policyType"
                                class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                            >
                            <label for="password-policy" class="ml-2 text-sm text-gray-700">
                                Password Policy
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input 
                                type="radio" 
                                id="session-policy"
                                name="policy-type"
                                value="session"
                                x-model="policyType"
                                class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                            >
                            <label for="session-policy" class="ml-2 text-sm text-gray-700">
                                Session Policy
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Password Policy Settings -->
                <div class="mt-6" x-show="policyType === 'password'">
                    <h4 class="text-sm font-medium text-gray-900 mb-4">Password Policy Settings</h4>
                    
                    <div class="space-y-4">
                        <!-- Minimum Length -->
                        <div>
                            <label for="min-length" class="block text-sm font-medium text-gray-700 mb-2">
                                Minimum Password Length
                            </label>
                            <input 
                                type="number" 
                                id="min-length"
                                x-model="passwordPolicy.minLength"
                                min="8"
                                max="128"
                                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            >
                            <p class="mt-1 text-xs text-gray-500">Minimum 8 characters, maximum 128</p>
                        </div>
                        
                        <!-- Complexity Requirements -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Complexity Requirements
                            </label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="require-uppercase"
                                        x-model="passwordPolicy.requireUppercase"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="require-uppercase" class="ml-2 text-sm text-gray-700">
                                        Require uppercase letters
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="require-lowercase"
                                        x-model="passwordPolicy.requireLowercase"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="require-lowercase" class="ml-2 text-sm text-gray-700">
                                        Require lowercase letters
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="require-numbers"
                                        x-model="passwordPolicy.requireNumbers"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="require-numbers" class="ml-2 text-sm text-gray-700">
                                        Require numbers
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="require-symbols"
                                        x-model="passwordPolicy.requireSymbols"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="require-symbols" class="ml-2 text-sm text-gray-700">
                                        Require special characters
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Password History -->
                        <div>
                            <label for="password-history" class="block text-sm font-medium text-gray-700 mb-2">
                                Password History
                            </label>
                            <input 
                                type="number" 
                                id="password-history"
                                x-model="passwordPolicy.historyCount"
                                min="0"
                                max="24"
                                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            >
                            <p class="mt-1 text-xs text-gray-500">Number of previous passwords to remember (0-24)</p>
                        </div>
                        
                        <!-- Password Expiry -->
                        <div>
                            <label for="password-expiry" class="block text-sm font-medium text-gray-700 mb-2">
                                Password Expiry (days)
                            </label>
                            <input 
                                type="number" 
                                id="password-expiry"
                                x-model="passwordPolicy.expiryDays"
                                min="0"
                                max="365"
                                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            >
                            <p class="mt-1 text-xs text-gray-500">0 = never expires</p>
                        </div>
                    </div>
                </div>
                
                <!-- Session Policy Settings -->
                <div class="mt-6" x-show="policyType === 'session'">
                    <h4 class="text-sm font-medium text-gray-900 mb-4">Session Policy Settings</h4>
                    
                    <div class="space-y-4">
                        <!-- Session Timeout -->
                        <div>
                            <label for="session-timeout" class="block text-sm font-medium text-gray-700 mb-2">
                                Session Timeout (minutes)
                            </label>
                            <input 
                                type="number" 
                                id="session-timeout"
                                x-model="sessionPolicy.timeoutMinutes"
                                min="5"
                                max="1440"
                                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            >
                            <p class="mt-1 text-xs text-gray-500">5-1440 minutes (24 hours)</p>
                        </div>
                        
                        <!-- Maximum Concurrent Sessions -->
                        <div>
                            <label for="max-sessions" class="block text-sm font-medium text-gray-700 mb-2">
                                Maximum Concurrent Sessions
                            </label>
                            <input 
                                type="number" 
                                id="max-sessions"
                                x-model="sessionPolicy.maxConcurrentSessions"
                                min="1"
                                max="10"
                                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            >
                            <p class="mt-1 text-xs text-gray-500">1-10 sessions per user</p>
                        </div>
                        
                        <!-- Session Security -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Session Security
                            </label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="require-mfa"
                                        x-model="sessionPolicy.requireMfa"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="require-mfa" class="ml-2 text-sm text-gray-700">
                                        Require MFA for new sessions
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="ip-binding"
                                        x-model="sessionPolicy.ipBinding"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="ip-binding" class="ml-2 text-sm text-gray-700">
                                        Bind sessions to IP address
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="device-fingerprinting"
                                        x-model="sessionPolicy.deviceFingerprinting"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="device-fingerprinting" class="ml-2 text-sm text-gray-700">
                                        Enable device fingerprinting
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Policy Scope -->
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Apply to
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input 
                                type="radio" 
                                id="all-tenants"
                                name="policy-scope"
                                value="all"
                                x-model="policyScope"
                                class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                            >
                            <label for="all-tenants" class="ml-2 text-sm text-gray-700">
                                All tenants
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input 
                                type="radio" 
                                id="specific-tenants"
                                name="policy-scope"
                                value="specific"
                                x-model="policyScope"
                                class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                            >
                            <label for="specific-tenants" class="ml-2 text-sm text-gray-700">
                                Specific tenants
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Tenant Selection -->
                <div class="mt-4" x-show="policyScope === 'specific'">
                    <label for="tenant-selection" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Tenants
                    </label>
                    <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-md p-2 bg-gray-50">
                        <template x-for="tenant in availableTenants" :key="tenant.id">
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    :value="tenant.id" 
                                    x-model="selectedTenants"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <label class="ml-2 text-sm text-gray-700" x-text="tenant.name"></label>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    @click="confirmEditPolicy()"
                    :disabled="!policyType || (policyScope === 'specific' && selectedTenants.length === 0)"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-save mr-2"></i>
                    Save Policy
                </button>
                <button 
                    @click="showEditPolicyModal = false"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Policy modal methods are now in the main securityPage component --}}
