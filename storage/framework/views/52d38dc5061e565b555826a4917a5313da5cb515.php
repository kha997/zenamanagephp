<?php $__env->startSection('title', 'Send Invitation'); ?>
<?php $__env->startSection('page-title', 'Send Invitation'); ?>
<?php $__env->startSection('page-description', 'Invite new users to join your organization'); ?>
<?php $__env->startSection('user-initials', 'PM'); ?>
<?php $__env->startSection('user-name', 'Project Manager'); ?>
<?php $__env->startSection('current-route', 'invitations'); ?>

<?php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Invitations Management',
        'url' => '/invitations'
    ],
    [
        'label' => 'Send Invitation',
        'url' => '/invitations/create'
    ]
];
$currentRoute = 'invitations';
?>

<?php $__env->startSection('content'); ?>
<div x-data="invitationForm()">
    <!-- Organization Info -->
    <div class="dashboard-card p-6 mb-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">Invite to Organization</h3>
                <p class="text-sm text-gray-600">Send an invitation to join your organization</p>
            </div>
        </div>
    </div>

    <!-- Invitation Form -->
    <div class="dashboard-card p-6">
        <form @submit.prevent="submitInvitation()">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-gray-400 mr-1"></i>
                            Email Address *
                        </label>
                        <input 
                            type="email" 
                            x-model="formData.email"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="user@example.com"
                            required
                        >
                    </div>

                    <!-- Name -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user text-gray-400 mr-1"></i>
                                First Name
                            </label>
                            <input 
                                type="text" 
                                x-model="formData.first_name"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="John"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user text-gray-400 mr-1"></i>
                                Last Name
                            </label>
                            <input 
                                type="text" 
                                x-model="formData.last_name"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="Doe"
                            >
                        </div>
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-tag text-gray-400 mr-1"></i>
                            Role *
                        </label>
                        <select 
                            x-model="formData.role"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            required
                        >
                            <option value="">Select Role</option>
                            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <!-- Project -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-project-diagram text-gray-400 mr-1"></i>
                            Project (Optional)
                        </label>
                        <select 
                            x-model="formData.project_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                            <option value="">No specific project</option>
                            <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($project->id); ?>"><?php echo e($project->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Message -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-comment text-gray-400 mr-1"></i>
                            Personal Message
                        </label>
                        <textarea 
                            x-model="formData.message"
                            rows="6"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-vertical"
                            placeholder="Add a personal message to the invitation..."
                        ></textarea>
                    </div>

                    <!-- Expiry -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock text-gray-400 mr-1"></i>
                            Expires In
                        </label>
                        <select 
                            x-model="formData.expires_in_days"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                            <option value="3">3 days</option>
                            <option value="7" selected>7 days</option>
                            <option value="14">14 days</option>
                            <option value="30">30 days</option>
                        </select>
                    </div>

                    <!-- Preview -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Invitation Preview</h4>
                        <div class="text-sm text-gray-600">
                            <p><strong>To:</strong> <span x-text="formData.email || 'user@example.com'"></span></p>
                            <p><strong>Role:</strong> <span x-text="getRoleLabel(formData.role)"></span></p>
                            <p><strong>Expires:</strong> <span x-text="getExpiryDate()"></span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                <a 
                    href="/invitations" 
                    class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors flex items-center"
                >
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Invitations
                </a>
                
                <div class="flex space-x-3">
                    <button 
                        type="button" 
                        @click="previewInvitation()"
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-eye mr-2"></i>
                        Preview Email
                    </button>
                    <button 
                        type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                        :disabled="isSubmitting"
                    >
                        <i class="fas fa-paper-plane mr-2" x-show="!isSubmitting"></i>
                        <i class="fas fa-spinner fa-spin mr-2" x-show="isSubmitting"></i>
                        <span x-text="isSubmitting ? 'Sending...' : 'Send Invitation'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function invitationForm() {
    return {
        formData: {
            email: '',
            first_name: '',
            last_name: '',
            role: '',
            project_id: '',
            message: '',
            expires_in_days: 7
        },
        isSubmitting: false,
        roles: <?php echo json_encode($roles, 15, 512) ?>,

        getRoleLabel(roleKey) {
            return this.roles[roleKey] || 'Select Role';
        },

        getExpiryDate() {
            const days = this.formData.expires_in_days || 7;
            const date = new Date();
            date.setDate(date.getDate() + parseInt(days));
            return date.toLocaleDateString();
        },

        previewInvitation() {
            console.log('Preview invitation:', this.formData);
            this.showNotification('Preview functionality coming soon!', 'info');
        },

        async submitInvitation() {
            this.isSubmitting = true;

            try {
                const response = await fetch('/invitations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(this.formData)
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('Invitation sent successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = '/invitations';
                    }, 1500);
                } else {
                    this.showNotification(result.message || 'Failed to send invitation', 'error');
                }
            } catch (error) {
                this.showNotification('An error occurred while sending the invitation', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },

        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                type === 'warning' ? 'bg-yellow-600' :
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/invitations/create.blade.php ENDPATH**/ ?>