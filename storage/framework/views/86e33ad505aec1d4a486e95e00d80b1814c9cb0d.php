
<div x-show="showEditModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModals"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            
            <form @submit.prevent="updateTenant">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-edit text-green-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Edit Tenant
                            </h3>
                            
                            <div class="space-y-4">
                                <!-- Name -->
                                <div>
                                    <label for="edit-name" class="block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" 
                                           id="edit-name"
                                           x-model="currentTenant.name"
                                           required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                </div>
                                
                                <!-- Domain -->
                                <div>
                                    <label for="edit-domain" class="block text-sm font-medium text-gray-700">Domain *</label>
                                    <input type="text" 
                                           id="edit-domain"
                                           x-model="currentTenant.domain"
                                           required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                </div>
                                
                                <!-- Owner Name -->
                                <div>
                                    <label for="edit-owner-name" class="block text-sm font-medium text-gray-700">Owner Name *</label>
                                    <input type="text" 
                                           id="edit-owner-name"
                                           x-model="currentTenant.ownerName"
                                           required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                </div>
                                
                                <!-- Owner Email -->
                                <div>
                                    <label for="edit-owner-email" class="block text-sm font-medium text-gray-700">Owner Email *</label>
                                    <input type="email" 
                                           id="edit-owner-email"
                                           x-model="currentTenant.ownerEmail"
                                           required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                </div>
                                
                                <!-- Plan -->
                                <div>
                                    <label for="edit-plan" class="block text-sm font-medium text-gray-700">Plan *</label>
                                    <select id="edit-plan"
                                            x-model="currentTenant.plan"
                                            required
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                        <option value="Basic">Basic</option>
                                        <option value="Professional">Professional</option>
                                        <option value="Enterprise">Enterprise</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Update Tenant
                    </button>
                    <button type="button"
                            @click="closeModals"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/_edit_modal.blade.php ENDPATH**/ ?>