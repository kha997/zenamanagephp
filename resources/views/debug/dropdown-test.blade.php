@extends('layouts.app')

@section('title', 'Dropdown Test')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold mb-6">Dropdown Test Page</h1>
            
            <!-- Test Results -->
            <div id="test-results" class="mb-6 p-4 bg-gray-100 rounded-lg">
                <h3 class="font-medium mb-2">Test Results:</h3>
                <div id="results-content">Loading...</div>
            </div>
            
            <!-- Test Dropdowns -->
            <div class="space-y-6">
                <!-- Test 1: Basic Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 1: Basic Dropdown</label>
                    <select id="test1" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test 2: Project Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 2: Project Dropdown</label>
                    <select id="test2" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select a project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Test 3: Dropdown with Event Listeners -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 3: With Event Listeners</label>
                    <select id="test3" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test Buttons -->
                <div class="flex space-x-4">
                    <button onclick="runTests()" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                        Run Tests
                    </button>
                    <button onclick="testClickEvents()" class="bg-green-600 text-white px-4 py-2 rounded-lg">
                        Test Click Events
                    </button>
                    <button onclick="checkCSS()" class="bg-purple-600 text-white px-4 py-2 rounded-lg">
                        Check CSS
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let testResults = [];

function logResult(test, result, details = '') {
    testResults.push({ test, result, details });
    console.log(`[${test}] ${result}: ${details}`);
}

function runTests() {
    testResults = [];
    const resultsContent = document.getElementById('results-content');
    
    // Test 1: Basic dropdown
    const test1 = document.getElementById('test1');
    logResult('Test 1', 'Basic Dropdown', `Options: ${test1.options.length}, Value: ${test1.value}`);
    
    // Test 2: Project dropdown
    const test2 = document.getElementById('test2');
    logResult('Test 2', 'Project Dropdown', `Options: ${test2.options.length}, Value: ${test2.value}`);
    
    // Test 3: Event listener dropdown
    const test3 = document.getElementById('test3');
    logResult('Test 3', 'Event Listener Dropdown', `Options: ${test3.options.length}, Value: ${test3.value}`);
    
    // Test visibility
    [test1, test2, test3].forEach((select, index) => {
        const isVisible = select.offsetWidth > 0 && select.offsetHeight > 0;
        const computedStyle = window.getComputedStyle(select);
        logResult(`Visibility ${index + 1}`, isVisible ? 'Visible' : 'Hidden', 
            `Display: ${computedStyle.display}, Visibility: ${computedStyle.visibility}`);
    });
    
    // Display results
    let html = '<ul class="space-y-2">';
    testResults.forEach(result => {
        const color = result.result === 'Visible' ? 'text-green-600' : 'text-red-600';
        html += `<li class="${color}"><strong>${result.test}:</strong> ${result.result} - ${result.details}</li>`;
    });
    html += '</ul>';
    
    resultsContent.innerHTML = html;
}

function testClickEvents() {
    const selects = [document.getElementById('test1'), document.getElementById('test2'), document.getElementById('test3')];
    
    selects.forEach((select, index) => {
        // Add click event listener
        select.addEventListener('click', function(e) {
            logResult(`Click Test ${index + 1}`, 'Click Event Fired', `Value: ${e.target.value}`);
        });
        
        // Add change event listener
        select.addEventListener('change', function(e) {
            logResult(`Change Test ${index + 1}`, 'Change Event Fired', `New Value: ${e.target.value}`);
        });
        
        // Try programmatic click
        select.click();
        logResult(`Programmatic Click ${index + 1}`, 'Programmatic Click Executed', `Current Value: ${select.value}`);
    });
}

function checkCSS() {
    const selects = [document.getElementById('test1'), document.getElementById('test2'), document.getElementById('test3')];
    
    selects.forEach((select, index) => {
        const computedStyle = window.getComputedStyle(select);
        const cssInfo = {
            display: computedStyle.display,
            visibility: computedStyle.visibility,
            pointerEvents: computedStyle.pointerEvents,
            zIndex: computedStyle.zIndex,
            position: computedStyle.position,
            overflow: computedStyle.overflow,
            height: computedStyle.height,
            width: computedStyle.width
        };
        
        logResult(`CSS Test ${index + 1}`, 'CSS Properties', JSON.stringify(cssInfo));
    });
}

// Run tests on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dropdown test page loaded');
    runTests();
});
</script>
@endsection
