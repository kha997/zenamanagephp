@extends('layouts.app')

@section('title', 'Test Template')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1>Test Template</h1>
    <p>This is a test template to verify script sections work.</p>
</div>
@endsection

@push('scripts')
    <script>
        console.log('=== TEST TEMPLATE SCRIPT ===');
        console.log('Script section is working!');
    </script>
@endpush
