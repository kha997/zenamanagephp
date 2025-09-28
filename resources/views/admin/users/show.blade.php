@extends('layouts.admin')

@section('title', 'User Details')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">User Details</h1>
    <div class="bg-white shadow-md rounded-lg p-6">
        <p class="text-gray-700">Loading user details...</p>
        {{-- This will be replaced with actual data from API --}}
        <pre x-data="{ user: {} }" x-init="
            fetch('/api/admin/users/' + window.location.pathname.split('/').pop())
                .then(response => response.json())
                .then(data => user = data.data)
                .catch(error => console.error('Error loading user details:', error));
        " x-text="JSON.stringify(user, null, 2)" class="bg-gray-100 p-4 rounded-md mt-4 text-sm"></pre>
    </div>
</div>
@endsection
