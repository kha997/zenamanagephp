@extends('layouts.app')

@section('title', 'Debug Task Create')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Debug Task Create</h1>
                    <p class="text-sm text-gray-600">Testing dropdown functionality</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow">
            <div class="p-6">
                <!-- Debug Info -->
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-medium text-blue-900 mb-2">Debug Information</h3>
                    <p><strong>Projects Count:</strong> {{ $projects->count() }}</p>
                    <p><strong>Users Count:</strong> {{ $users->count() }}</p>
                    <p><strong>User ID:</strong> {{ Auth::id() }}</p>
                    <p><strong>Tenant ID:</strong> {{ Auth::user()->tenant_id ?? 'N/A' }}</p>
                </div>

                <!-- Test Dropdowns -->
                <div class="space-y-6">
                    <!-- Simple Test Dropdown -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Simple Test Dropdown</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select option</option>
                            <option value="1">Option 1</option>
                            <option value="2">Option 2</option>
                            <option value="3">Option 3</option>
                        </select>
                    </div>

                    <!-- Project Dropdown -->
                    <div>
                        <label for="project_id" class="block text-sm font-medium text-gray-700 mb-2">Project Dropdown</label>
                        <select id="project_id" 
                                name="project_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Test Button -->
                    <div>
                        <button onclick="testDropdowns()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Test Dropdowns
                        </button>
                    </div>

                    <!-- Results -->
                    <div id="results" class="p-4 bg-gray-100 rounded-lg"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testDropdowns() {
    const results = document.getElementById('results');
    const projectSelect = document.getElementById('project_id');
    
    let html = '<h3 class="font-medium mb-2">Test Results:</h3>';
    
    // Test project dropdown
    html += '<div class="mb-4">';
    html += '<h4 class="font-medium text-gray-700">Project Dropdown:</h4>';
    html += `<p>ID: ${projectSelect.id}</p>`;
    html += `<p>Options count: ${projectSelect.options.length}</p>`;
    html += `<p>Selected value: ${projectSelect.value}</p>`;
    html += `<p>Selected text: ${projectSelect.options[projectSelect.selectedIndex].text}</p>`;
    html += `<p>Is disabled: ${projectSelect.disabled}</p>`;
    html += `<p>Is visible: ${projectSelect.offsetWidth > 0 && projectSelect.offsetHeight > 0}</p>`;
    html += `<p>Computed style display: ${window.getComputedStyle(projectSelect).display}</p>`;
    html += `<p>Computed style visibility: ${window.getComputedStyle(projectSelect).visibility}</p>`;
    html += `<p>Computed style pointer-events: ${window.getComputedStyle(projectSelect).pointerEvents}</p>`;
    html += '</div>';
    
    // Test click event
    html += '<div class="mb-4">';
    html += '<h4 class="font-medium text-gray-700">Click Test:</h4>';
    html += '<button onclick="testClick()" class="bg-green-600 text-white px-3 py-1 rounded text-sm">Test Click Event</button>';
    html += '</div>';
    
    results.innerHTML = html;
}

function testClick() {
    const projectSelect = document.getElementById('project_id');
    
    // Try to open dropdown programmatically
    projectSelect.focus();
    projectSelect.click();
    
    // Log to console
    console.log('Dropdown clicked programmatically');
    console.log('Current value:', projectSelect.value);
    console.log('Options:', Array.from(projectSelect.options).map(opt => opt.text));
}

// Test on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Debug page loaded');
    testDropdowns();
});
</script>
@endsection
