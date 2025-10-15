@extends('layouts.auth-layout')

@section('title', 'Verify Email - ZenaManage')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Verify Your Email Address
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                We've sent a verification link to your email address. Please check your inbox and click the link to verify your account.
            </p>
        </div>
        
        <div class="mt-8 space-y-6">
            @if (session('message'))
                <div class="rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                {{ session('message') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white shadow rounded-lg p-6">
                <div class="text-center">
                    <div class="mx-auto h-16 w-16 flex items-center justify-center rounded-full bg-gray-100 mb-4">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                        Check Your Email
                    </h3>
                    
                    <p class="text-sm text-gray-600 mb-6">
                        We've sent a verification link to <strong>{{ auth()->user()->email }}</strong>. 
                        Please check your inbox and click the link to verify your account.
                    </p>
                    
                    <div class="space-y-4">
                        <form method="POST" action="{{ route('verification.send') }}" id="resendForm">
                            @csrf
                            <button type="submit" id="resendButton"
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="resendButtonText">Resend Verification Email</span>
                                <svg id="resendSpinner" class="hidden animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <a href="{{ route('logout') }}" class="text-sm text-gray-600 hover:text-gray-900">
                                Sign out and use a different email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">
                    ← Back to login
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Handle resend verification email
document.getElementById('resendForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const resendButton = document.getElementById('resendButton');
    const resendButtonText = document.getElementById('resendButtonText');
    const resendSpinner = document.getElementById('resendSpinner');
    
    // Show loading state
    resendButton.disabled = true;
    resendButtonText.textContent = 'Sending...';
    resendSpinner.classList.remove('hidden');
    
    try {
        const response = await fetch('/email/verification-notification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Request-Id': generateRequestId()
            }
        });
        
        if (response.ok) {
            // Show success message
            showSuccess('Verification email sent successfully!');
        } else {
            // Show error message
            showError('Failed to send verification email');
        }
    } catch (error) {
        console.error('Resend verification error:', error);
        showError('Network error. Please try again.');
    } finally {
        // Reset button state
        resendButton.disabled = false;
        resendButtonText.textContent = 'Resend Verification Email';
        resendSpinner.classList.add('hidden');
    }
});

function showError(message) {
    // Remove existing messages
    const existingError = document.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Create error message element
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message rounded-md bg-red-50 p-4';
    errorDiv.innerHTML = `
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800">${message}</p>
            </div>
        </div>
    `;
    
    // Insert error message after the form
    const form = document.getElementById('resendForm');
    form.parentNode.insertBefore(errorDiv, form.nextSibling);
}

function showSuccess(message) {
    // Remove existing messages
    const existingSuccess = document.querySelector('.success-message');
    if (existingSuccess) {
        existingSuccess.remove();
    }
    
    // Create success message element
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message rounded-md bg-green-50 p-4';
    successDiv.innerHTML = `
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-green-800">${message}</p>
            </div>
        </div>
    `;
    
    // Insert success message after the form
    const form = document.getElementById('resendForm');
    form.parentNode.insertBefore(successDiv, form.nextSibling);
}

function generateRequestId() {
    return 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}
</script>
@endsection
