@extends('layouts.app')

@section('title', 'CSS Conflict Check')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold mb-6">CSS Conflict Check</h1>
            
            <!-- CSS Override Test -->
            <div class="mb-6 p-4 bg-yellow-50 rounded-lg">
                <h3 class="font-medium text-yellow-900 mb-2">CSS Override Test</h3>
                <p class="text-yellow-800">This page tests various CSS properties that might prevent dropdowns from working.</p>
            </div>
            
            <!-- Test Results -->
            <div id="css-results" class="mb-6 p-4 bg-gray-100 rounded-lg">
                <h3 class="font-medium mb-2">CSS Test Results:</h3>
                <div id="css-results-content">Click "Check CSS Conflicts" to start...</div>
            </div>
            
            <!-- Test Buttons -->
            <div class="mb-6 flex space-x-4">
                <button onclick="checkCSSConflicts()" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                    Check CSS Conflicts
                </button>
                <button onclick="testCSSOverrides()" class="bg-green-600 text-white px-4 py-2 rounded-lg">
                    Test CSS Overrides
                </button>
                <button onclick="resetCSS()" class="bg-red-600 text-white px-4 py-2 rounded-lg">
                    Reset CSS
                </button>
            </div>
            
            <!-- Test Dropdowns with Different CSS -->
            <div class="space-y-6">
                <!-- Test 1: Normal Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 1: Normal Dropdown</label>
                    <select id="normal-dropdown" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test 2: Dropdown with pointer-events: none -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 2: pointer-events: none</label>
                    <select id="pointer-events-none" class="w-full px-3 py-2 border border-gray-300 rounded-lg" style="pointer-events: none;">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test 3: Dropdown with overflow: hidden -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 3: overflow: hidden</label>
                    <select id="overflow-hidden" class="w-full px-3 py-2 border border-gray-300 rounded-lg" style="overflow: hidden;">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test 4: Dropdown with z-index: -1 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 4: z-index: -1</label>
                    <select id="z-index-negative" class="w-full px-3 py-2 border border-gray-300 rounded-lg" style="z-index: -1;">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test 5: Dropdown with display: none -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 5: display: none</label>
                    <select id="display-none" class="w-full px-3 py-2 border border-gray-300 rounded-lg" style="display: none;">
                        <option value="">Select option</option>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                    </select>
                </div>
                
                <!-- Test 6: Project Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 6: Project Dropdown</label>
                    <select id="project-dropdown" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select a project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cssResults = [];

function logCSSResult(test, result, details = '') {
    cssResults.push({ test, result, details });
    console.log(`[CSS ${test}] ${result}: ${details}`);
}

function checkCSSConflicts() {
    cssResults = [];
    const resultsContent = document.getElementById('css-results-content');
    
    logCSSResult('CSS Check', 'Starting CSS conflict check...', '');
    
    const dropdowns = [
        { id: 'normal-dropdown', name: 'Normal Dropdown' },
        { id: 'pointer-events-none', name: 'pointer-events: none' },
        { id: 'overflow-hidden', name: 'overflow: hidden' },
        { id: 'z-index-negative', name: 'z-index: -1' },
        { id: 'display-none', name: 'display: none' },
        { id: 'project-dropdown', name: 'Project Dropdown' }
    ];
    
    dropdowns.forEach(dropdown => {
        const element = document.getElementById(dropdown.id);
        if (element) {
            const computedStyle = window.getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            
            logCSSResult(dropdown.name, 'CSS Properties', 
                `Display: ${computedStyle.display}, Visibility: ${computedStyle.visibility}, PointerEvents: ${computedStyle.pointerEvents}`);
            
            logCSSResult(dropdown.name, 'Layout Properties', 
                `Width: ${rect.width}, Height: ${rect.height}, ZIndex: ${computedStyle.zIndex}`);
            
            logCSSResult(dropdown.name, 'Overflow Properties', 
                `Overflow: ${computedStyle.overflow}, OverflowX: ${computedStyle.overflowX}, OverflowY: ${computedStyle.overflowY}`);
            
            // Test if dropdown is clickable
            const isClickable = computedStyle.pointerEvents !== 'none' && 
                               computedStyle.display !== 'none' && 
                               computedStyle.visibility !== 'hidden' &&
                               rect.width > 0 && rect.height > 0;
            
            logCSSResult(dropdown.name, 'Clickability', 
                `Clickable: ${isClickable}`);
        }
    });
    
    // Display results
    let html = '<ul class="space-y-2">';
    cssResults.forEach(result => {
        const color = result.result === 'Clickability' && result.details.includes('Clickable: true') ? 'text-green-600' :
                     result.result === 'Clickability' && result.details.includes('Clickable: false') ? 'text-red-600' : 'text-blue-600';
        html += `<li class="${color}"><strong>${result.test}:</strong> ${result.result} - ${result.details}</li>`;
    });
    html += '</ul>';
    
    resultsContent.innerHTML = html;
}

function testCSSOverrides() {
    const projectDropdown = document.getElementById('project-dropdown');
    
    // Test different CSS overrides
    const overrides = [
        { name: 'pointer-events: auto', style: 'pointer-events: auto !important;' },
        { name: 'z-index: 9999', style: 'z-index: 9999 !important;' },
        { name: 'overflow: visible', style: 'overflow: visible !important;' },
        { name: 'position: relative', style: 'position: relative !important;' }
    ];
    
    overrides.forEach((override, index) => {
        setTimeout(() => {
            projectDropdown.style.cssText = override.style;
            logCSSResult('CSS Override', override.name, `Applied ${override.name} to project dropdown`);
            
            // Test click after override
            projectDropdown.click();
            logCSSResult('CSS Override', override.name, `Clicked dropdown after applying ${override.name}`);
        }, index * 1000);
    });
}

function resetCSS() {
    const projectDropdown = document.getElementById('project-dropdown');
    projectDropdown.style.cssText = '';
    logCSSResult('CSS Reset', 'Reset', 'Reset all CSS overrides on project dropdown');
}

// Run initial check
document.addEventListener('DOMContentLoaded', function() {
    checkCSSConflicts();
});
</script>
@endsection
