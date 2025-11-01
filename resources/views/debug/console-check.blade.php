@extends('layouts.app')

@section('title', 'Console Error Check')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold mb-6">Console Error Check</h1>
            
            <!-- Instructions -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-medium text-blue-900 mb-2">Instructions:</h3>
                <ol class="list-decimal list-inside space-y-1 text-blue-800">
                    <li>Open Developer Tools (F12)</li>
                    <li>Go to Console tab</li>
                    <li>Click "Start Monitoring" button below</li>
                    <li>Try clicking the dropdowns</li>
                    <li>Check for any errors in console</li>
                </ol>
            </div>
            
            <!-- Console Monitor -->
            <div class="mb-6">
                <button onclick="startMonitoring()" class="bg-green-600 text-white px-4 py-2 rounded-lg mr-4">
                    Start Monitoring
                </button>
                <button onclick="clearConsole()" class="bg-red-600 text-white px-4 py-2 rounded-lg mr-4">
                    Clear Console
                </button>
                <button onclick="testDropdowns()" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                    Test Dropdowns
                </button>
            </div>
            
            <!-- Console Output -->
            <div id="console-output" class="mb-6 p-4 bg-gray-900 text-green-400 rounded-lg font-mono text-sm max-h-64 overflow-y-auto">
                <div>Console output will appear here...</div>
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
                
                <!-- Test 3: Dropdown with Custom Events -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test 3: Custom Events</label>
                    <select id="test3" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
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
let originalConsole = {
    log: console.log,
    error: console.error,
    warn: console.warn,
    info: console.info
};

let consoleOutput = document.getElementById('console-output');
let isMonitoring = false;

function logToOutput(message, type = 'log') {
    const timestamp = new Date().toLocaleTimeString();
    const color = type === 'error' ? 'text-red-400' : 
                 type === 'warn' ? 'text-yellow-400' : 
                 type === 'info' ? 'text-blue-400' : 'text-green-400';
    
    const logEntry = document.createElement('div');
    logEntry.className = color;
    logEntry.textContent = `[${timestamp}] ${message}`;
    
    consoleOutput.appendChild(logEntry);
    consoleOutput.scrollTop = consoleOutput.scrollHeight;
}

function startMonitoring() {
    if (isMonitoring) {
        logToOutput('Monitoring already started', 'warn');
        return;
    }
    
    isMonitoring = true;
    logToOutput('Console monitoring started', 'info');
    
    // Override console methods
    console.log = function(...args) {
        originalConsole.log.apply(console, args);
        logToOutput('LOG: ' + args.join(' '), 'log');
    };
    
    console.error = function(...args) {
        originalConsole.error.apply(console, args);
        logToOutput('ERROR: ' + args.join(' '), 'error');
    };
    
    console.warn = function(...args) {
        originalConsole.warn.apply(console, args);
        logToOutput('WARN: ' + args.join(' '), 'warn');
    };
    
    console.info = function(...args) {
        originalConsole.info.apply(console, args);
        logToOutput('INFO: ' + args.join(' '), 'info');
    };
    
    // Monitor for unhandled errors
    window.addEventListener('error', function(e) {
        logToOutput(`UNHANDLED ERROR: ${e.message} at ${e.filename}:${e.lineno}`, 'error');
    });
    
    // Monitor for unhandled promise rejections
    window.addEventListener('unhandledrejection', function(e) {
        logToOutput(`UNHANDLED PROMISE REJECTION: ${e.reason}`, 'error');
    });
}

function clearConsole() {
    consoleOutput.innerHTML = '<div>Console cleared...</div>';
    originalConsole.log('Console cleared');
}

function testDropdowns() {
    logToOutput('Testing dropdowns...', 'info');
    
    const selects = [document.getElementById('test1'), document.getElementById('test2'), document.getElementById('test3')];
    
    selects.forEach((select, index) => {
        try {
            logToOutput(`Testing dropdown ${index + 1}...`, 'info');
            
            // Test basic properties
            logToOutput(`Dropdown ${index + 1} - Options: ${select.options.length}`, 'log');
            logToOutput(`Dropdown ${index + 1} - Value: ${select.value}`, 'log');
            logToOutput(`Dropdown ${index + 1} - Disabled: ${select.disabled}`, 'log');
            
            // Test visibility
            const rect = select.getBoundingClientRect();
            logToOutput(`Dropdown ${index + 1} - Visible: ${rect.width > 0 && rect.height > 0}`, 'log');
            
            // Test click
            select.addEventListener('click', function(e) {
                logToOutput(`Dropdown ${index + 1} - Click event fired`, 'log');
            });
            
            // Test change
            select.addEventListener('change', function(e) {
                logToOutput(`Dropdown ${index + 1} - Change event fired, new value: ${e.target.value}`, 'log');
            });
            
            // Test focus
            select.addEventListener('focus', function(e) {
                logToOutput(`Dropdown ${index + 1} - Focus event fired`, 'log');
            });
            
        } catch (error) {
            logToOutput(`Error testing dropdown ${index + 1}: ${error.message}`, 'error');
        }
    });
}

// Auto-start monitoring on page load
document.addEventListener('DOMContentLoaded', function() {
    logToOutput('Page loaded, starting monitoring...', 'info');
    startMonitoring();
    testDropdowns();
});
</script>
@endsection
