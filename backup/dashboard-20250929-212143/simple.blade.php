{{-- Simple Dashboard Test --}}
@extends('layouts.admin')

@section('title', 'Simple Dashboard')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Simple Dashboard Test</h1>
    
    <div x-data="{ show: true }">
        <p>Alpine.js working: <span x-text="show ? 'YES' : 'NO'"></span></p>
        <button @click="show = !show" class="bg-blue-500 text-white px-4 py-2 rounded">
            Toggle
        </button>
        
        <div x-show="show" class="mt-4 p-4 bg-green-100 rounded">
            <p>Alpine.js is working correctly!</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    console.log('Simple dashboard script loaded');
</script>
@endpush
