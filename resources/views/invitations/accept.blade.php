@extends('layouts.auth')

@section('title', 'Accept Invitation')
@section('page-title', 'Join Our Team')
@section('page-description', 'Complete your registration to join our organization')

@section('content')
<div x-data="acceptInvitation()" class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-envelope-open text-blue-600 text-xl"></i>
            </div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                You're Invited!
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Complete your registration to join <strong>{{ $invitation->organization->name }}</strong>
            </p>
        </div>

        <!-- Invitation Details -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="text-center mb-6">
                <div class="mx-auto h-16 w-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <span class="text-2xl font-bold text-gray-600">
                        {{ strtoupper(substr($invitation->email, 0, 2)) }}
                    </span>
                </div>
                <h3 class="text-lg font-medium text-gray-900">
                    {{ $invitation->full_name ?: $invitation->email }}
                </h3>
                <p class="text-sm text-gray-600">{{ $invitation->email }}</p>
            </div>

            <div class="space-y-3 mb-6">
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <span class="text-sm font-medium text-gray-600">Role</span>
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                        {{ $invitation->getRoleDisplayName() }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <span class="text-sm font-medium text-gray-600">Project</span>
                    <span class="text-sm text-gray-900">{{ $invitation->getProjectName() }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <span class="text-sm font-medium text-gray-600">Invited by</span>
                    <span class="text-sm text-gray-900">{{ $invitation->getInviterName() }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm font-medium text-gray-600">Expires</span>
                    <span class="text-sm text-gray-900">{{ $invitation->expires_at->format('M d, Y') }}</span>
                </div>
            </div>

            @if($invitation->message)
            <div class="bg-blue-50 p-4 rounded-lg mb-6">
                <h4 class="text-sm font-medium text-blue-900 mb-2">Personal Message</h4>
                <p class="text-sm text-blue-800">{{ $invitation->message }}</p>
            </div>
            @endif
        </div>

        <!-- Registration Form -->
        <form @submit.prevent="submitRegistration()" class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="space-y-6">
                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock text-gray-400 mr-1"></i>
                        Create Password *
                    </label>
                    <input 
                        id="password"
                        type="password" 
                        x-model="formData.password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        placeholder="Enter a strong password"
                        required
                        minlength="8"
                    >
                    <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock text-gray-400 mr-1"></i>
                        Confirm Password *
                    </label>
                    <input 
                        id="password_confirmation"
                        type="password" 
                        x-model="formData.password_confirmation"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        placeholder="Confirm your password"
                        required
                    >
                </div>

                <!-- Additional Info -->
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone text-gray-400 mr-1"></i>
                            Phone Number
                        </label>
                        <input 
                            id="phone"
                            type="tel" 
                            x-model="formData.phone"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="+1 (555) 123-4567"
                        >
                    </div>
                    <div>
                        <label for="job_title" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-briefcase text-gray-400 mr-1"></i>
                            Job Title
                        </label>
                        <input 
                            id="job_title"
                            type="text" 
                            x-model="formData.job_title"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="e.g., Software Engineer"
                        >
                    </div>
                </div>

                <!-- Terms -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input 
                            id="terms" 
                            type="checkbox" 
                            x-model="formData.accept_terms"
                            class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded"
                            required
                        >
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms" class="text-gray-700">
                            I agree to the <a href="#" class="text-blue-600 hover:text-blue-500">Terms of Service</a> 
                            and <a href="#" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-6">
                <button 
                    type="submit" 
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                    :disabled="isSubmitting"
                >
                    <i class="fas fa-check mr-2" x-show="!isSubmitting"></i>
                    <i class="fas fa-spinner fa-spin mr-2" x-show="isSubmitting"></i>
                    <span x-text="isSubmitting ? 'Creating Account...' : 'Accept Invitation & Create Account'"></span>
                </button>
            </div>
        </form>

        <!-- Footer -->
        <div class="text-center">
            <p class="text-xs text-gray-500">
                Having trouble? Contact your organization administrator.
            </p>
        </div>
    </div>
</div>

<script>
function acceptInvitation() {
    return {
        formData: {
            password: '',
            password_confirmation: '',
            phone: '',
            job_title: '',
            accept_terms: false
        },
        isSubmitting: false,

        async submitRegistration() {
            if (!this.formData.accept_terms) {
                this.showNotification('Please accept the terms and conditions', 'error');
                return;
            }

            if (this.formData.password !== this.formData.password_confirmation) {
                this.showNotification('Passwords do not match', 'error');
                return;
            }

            this.isSubmitting = true;

            try {
                const response = await fetch(`/invitations/accept/{{ $invitation->token }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(this.formData)
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('Account created successfully! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 2000);
                } else {
                    this.showNotification(result.message || 'Failed to create account', 'error');
                }
            } catch (error) {
                this.showNotification('An error occurred while creating your account', 'error');
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
            }, 5000);
        }
    }
}
</script>
@endsection
