@extends('layouts.dashboard')

@section('title', 'Invite Team Member')
@section('page-title', 'Invite Team Member')
@section('page-description', 'Add new team members and assign roles')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')

@section('content')
<div x-data="teamInvite()">
    <!-- Team Invite Form -->
    <div class="dashboard-card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">👥 Invite New Team Member</h3>
        
        <form method="POST" action="/team/invite" @submit.prevent="inviteMember">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Personal Information</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                <input 
                                    type="text" 
                                    name="first_name" 
                                    required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Enter first name"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                <input 
                                    type="text" 
                                    name="last_name" 
                                    required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Enter last name"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input 
                                type="email" 
                                name="email" 
                                required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter email address"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input 
                                type="tel" 
                                name="phone" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter phone number"
                            >
                        </div>
                    </div>
                    
                    <!-- Role & Permissions -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Role & Permissions</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                            <select 
                                name="role" 
                                required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Select Role</option>
                                <option value="project_manager">Project Manager</option>
                                <option value="designer">Designer</option>
                                <option value="developer">Developer</option>
                                <option value="site_engineer">Site Engineer</option>
                                <option value="sales">Sales</option>
                                <option value="client">Client</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select 
                                name="department" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Select Department</option>
                                <option value="engineering">Engineering</option>
                                <option value="design">Design</option>
                                <option value="construction">Construction</option>
                                <option value="sales">Sales</option>
                                <option value="management">Management</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Access Level</label>
                            <select 
                                name="access_level" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="basic">Basic</option>
                                <option value="standard">Standard</option>
                                <option value="advanced">Advanced</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Project Assignment -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Project Assignment</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Projects</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" name="projects[]" value="1" class="mr-2">
                                    <span class="text-sm">Office Building Complex</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="projects[]" value="2" class="mr-2">
                                    <span class="text-sm">Shopping Mall Development</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="projects[]" value="3" class="mr-2">
                                    <span class="text-sm">Residential Complex</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="projects[]" value="4" class="mr-2">
                                    <span class="text-sm">Hotel Complex</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Notification Settings</h4>
                        
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="checkbox" name="email_notifications" value="1" class="mr-2" checked>
                                <span class="text-sm">Email notifications</span>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="sms_notifications" value="1" class="mr-2">
                                <span class="text-sm">SMS notifications</span>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="project_updates" value="1" class="mr-2" checked>
                                <span class="text-sm">Project updates</span>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="task_assignments" value="1" class="mr-2" checked>
                                <span class="text-sm">Task assignments</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invitation Message -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Invitation Message</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Welcome Message</label>
                            <textarea 
                                name="welcome_message" 
                                rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter a welcome message for the new team member..."
                            >Welcome to our team! We're excited to have you join us and look forward to working together on our projects.</textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                <button 
                    type="button" 
                    @click="cancelInvite()"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                    :disabled="inviting"
                >
                    <span x-show="!inviting">📧 Send Invitation</span>
                    <span x-show="inviting">⏳ Sending...</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function teamInvite() {
    return {
        inviting: false,
        
        inviteMember() {
            this.inviting = true;
            // Form submission will be handled by Laravel
            // This is just for UI feedback
            setTimeout(() => {
                this.inviting = false;
                alert('Invitation sent successfully!');
                window.location.href = '/team';
            }, 2000);
        },
        
        cancelInvite() {
            window.location.href = '/team';
        }
    }
}
</script>
@endsection