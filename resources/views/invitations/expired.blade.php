{{-- Invitation Expired View --}}
@extends('layouts.auth-layout')

@section('title', 'Invitation Expired')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 text-center">
        <!-- Icon -->
        <div class="mx-auto h-16 w-16 bg-red-100 rounded-full flex items-center justify-center">
            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
        </div>
        
        <!-- Title -->
        <h2 class="text-3xl font-extrabold text-gray-900">
            Invitation Expired
        </h2>
        
        <!-- Message -->
        <p class="mt-4 text-sm text-gray-600">
            This invitation link has expired or is no longer valid.
        </p>
        
        <p class="mt-2 text-sm text-gray-500">
            Please contact your organization administrator to request a new invitation.
        </p>
        
        <!-- Actions -->
        <div class="mt-6">
            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i>
                Go to Login
            </a>
        </div>
    </div>
</div>
@endsection

