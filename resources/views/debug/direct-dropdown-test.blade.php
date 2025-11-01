@extends('layouts.app')

@section('title', 'Direct Dropdown Test')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold mb-6">Direct Dropdown Test</h1>
            
            <!-- Test Results -->
            <div id="test-results" class="mb-6 p-4 bg-gray-100 rounded-lg">
                <h3 class="font-medium mb-2">Test Results:</h3>
                <div id="results-content">Click "Run Direct Test" to start...</div>
            </div>
            
            <!-- Test Buttons -->
            <div class="mb-6 flex space-x-4">
                <button onclick="runDirectTest()" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                    Run Direct Test
                </button>
                <button onclick="testClickProgrammatically()" class="bg-green-600 text-white px-4 py-2 rounded-lg">
                    Test Click Programmatically
                </button>
                <button onclick="testFocusAndBlur()" class="bg-purple-600 text-white px-4 py-2 rounded-lg">
                    Test Focus/Blur
                </button>
                <button onclick="testKeyboardEvents()" class="bg-orange-600 text-white px-4 py-2 rounded-lg">
                    Test Keyboard Events
                </button>
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
                
                <!-- Test 3: Dropdown with Custom Attributes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 3: Custom Attributes</label>
                    <select id="test3" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                            tabindex="0"
                            autocomplete="off"
                            data-test="dropdown">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test 4: Dropdown with Event Listeners -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 4: Event Listeners</label>
                    <select id="test4" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
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

function runDirectTest() {
    testResults = [];
    const resultsContent = document.getElementById('results-content');
    
    logResult('Direct Test', 'Starting direct dropdown test...', '');
    
    // Test all dropdowns
    const selects = [
        document.getElementById('test1'),
        document.getElementById('test2'),
        document.getElementById('test3'),
        document.getElementById('test4')
    ];
    
    selects.forEach((select, index) => {
        try {
            // Basic properties
            logResult(`Dropdown ${index + 1}`, 'Basic Properties', 
                `ID: ${select.id}, Options: ${select.options.length}, Value: ${select.value}`);
            
            // Visibility test
            const rect = select.getBoundingClientRect();
            const isVisible = rect.width > 0 && rect.height > 0;
            logResult(`Dropdown ${index + 1}`, 'Visibility', 
                `Visible: ${isVisible}, Width: ${rect.width}, Height: ${rect.height}`);
            
            // CSS properties
            const computedStyle = window.getComputedStyle(select);
            logResult(`Dropdown ${index + 1}`, 'CSS Properties', 
                `Display: ${computedStyle.display}, Visibility: ${computedStyle.visibility}, PointerEvents: ${computedStyle.pointerEvents}`);
            
            // Event listeners test
            const hasClickListeners = select.onclick !== null || select.addEventListener !== undefined;
            logResult(`Dropdown ${index + 1}`, 'Event Listeners', 
                `Has click listeners: ${hasClickListeners}`);
            
            // Accessibility test
            const isAccessible = select.tabIndex >= 0 && !select.disabled;
            logResult(`Dropdown ${index + 1}`, 'Accessibility', 
                `TabIndex: ${select.tabIndex}, Disabled: ${select.disabled}, Accessible: ${isAccessible}`);
            
        } catch (error) {
            logResult(`Dropdown ${index + 1}`, 'Error', error.message);
        }
    });
    
    // Display results
    let html = '<ul class="space-y-2">';
    testResults.forEach(result => {
        const color = result.result === 'Error' ? 'text-red-600' : 
                     result.result === 'Visibility' && result.details.includes('Visible: true') ? 'text-green-600' :
                     result.result === 'Visibility' && result.details.includes('Visible: false') ? 'text-red-600' : 'text-blue-600';
        html += `<li class="${color}"><strong>${result.test}:</strong> ${result.result} - ${result.details}</li>`;
    });
    html += '</ul>';
    
    resultsContent.innerHTML = html;
}

function testClickProgrammatically() {
    const selects = [
        document.getElementById('test1'),
        document.getElementById('test2'),
        document.getElementById('test3'),
        document.getElementById('test4')
    ];
    
    selects.forEach((select, index) => {
        try {
            // Focus first
            select.focus();
            logResult(`Programmatic Click ${index + 1}`, 'Focus', `Focused on dropdown ${index + 1}`);
            
            // Then click
            select.click();
            logResult(`Programmatic Click ${index + 1}`, 'Click', `Clicked dropdown ${index + 1}`);
            
            // Check if dropdown opened (this is tricky to detect)
            const isOpen = select.matches(':focus') && document.activeElement === select;
            logResult(`Programmatic Click ${index + 1}`, 'Open Check', `Dropdown ${index + 1} focused: ${isOpen}`);
            
        } catch (error) {
            logResult(`Programmatic Click ${index + 1}`, 'Error', error.message);
        }
    });
}

function testFocusAndBlur() {
    const selects = [
        document.getElementById('test1'),
        document.getElementById('test2'),
        document.getElementById('test3'),
        document.getElementById('test4')
    ];
    
    selects.forEach((select, index) => {
        try {
            // Test focus
            select.focus();
            const isFocused = document.activeElement === select;
            logResult(`Focus Test ${index + 1}`, 'Focus', `Dropdown ${index + 1} focused: ${isFocused}`);
            
            // Test blur
            select.blur();
            const isBlurred = document.activeElement !== select;
            logResult(`Focus Test ${index + 1}`, 'Blur', `Dropdown ${index + 1} blurred: ${isBlurred}`);
            
        } catch (error) {
            logResult(`Focus Test ${index + 1}`, 'Error', error.message);
        }
    });
}

function testKeyboardEvents() {
    const selects = [
        document.getElementById('test1'),
        document.getElementById('test2'),
        document.getElementById('test3'),
        document.getElementById('test4')
    ];
    
    selects.forEach((select, index) => {
        try {
            // Focus the select
            select.focus();
            
            // Test arrow down
            const arrowDownEvent = new KeyboardEvent('keydown', { key: 'ArrowDown' });
            select.dispatchEvent(arrowDownEvent);
            logResult(`Keyboard Test ${index + 1}`, 'Arrow Down', `Arrow down event dispatched`);
            
            // Test enter
            const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' });
            select.dispatchEvent(enterEvent);
            logResult(`Keyboard Test ${index + 1}`, 'Enter', `Enter event dispatched`);
            
            // Test space
            const spaceEvent = new KeyboardEvent('keydown', { key: ' ' });
            select.dispatchEvent(spaceEvent);
            logResult(`Keyboard Test ${index + 1}`, 'Space', `Space event dispatched`);
            
        } catch (error) {
            logResult(`Keyboard Test ${index + 1}`, 'Error', error.message);
        }
    });
}

// Add event listeners to test4
document.addEventListener('DOMContentLoaded', function() {
    const test4 = document.getElementById('test4');
    
    test4.addEventListener('click', function(e) {
        logResult('Event Test 4', 'Click Event', `Click event fired on test4`);
    });
    
    test4.addEventListener('focus', function(e) {
        logResult('Event Test 4', 'Focus Event', `Focus event fired on test4`);
    });
    
    test4.addEventListener('change', function(e) {
        logResult('Event Test 4', 'Change Event', `Change event fired on test4, new value: ${e.target.value}`);
    });
    
    test4.addEventListener('keydown', function(e) {
        logResult('Event Test 4', 'Keydown Event', `Keydown event fired on test4, key: ${e.key}`);
    });
    
    // Run initial test
    runDirectTest();
});
</script>
@endsection
