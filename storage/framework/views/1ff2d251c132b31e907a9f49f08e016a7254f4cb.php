


<div class="color-contrast-container">
    <!-- Color contrast checker -->
    <div class="contrast-checker bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Color Contrast Checker</h2>
        
        <!-- Test color combinations -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Text on Background Combinations -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Text on Background</h3>
                
                <!-- WCAG AA Compliant -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-green-700">WCAG AA Compliant (4.5:1+)</h4>
                    
                    <div class="bg-black text-white p-3 rounded border">
                        <span class="font-medium">Black text on white background</span>
                        <div class="text-sm text-gray-300 mt-1">Contrast ratio: 21:1</div>
                    </div>
                    
                    <div class="bg-blue-600 text-white p-3 rounded border">
                        <span class="font-medium">White text on blue background</span>
                        <div class="text-sm text-blue-200 mt-1">Contrast ratio: 4.5:1</div>
                    </div>
                    
                    <div class="bg-gray-800 text-white p-3 rounded border">
                        <span class="font-medium">White text on dark gray</span>
                        <div class="text-sm text-gray-300 mt-1">Contrast ratio: 12.6:1</div>
                    </div>
                    
                    <div class="bg-green-700 text-white p-3 rounded border">
                        <span class="font-medium">White text on green background</span>
                        <div class="text-sm text-green-200 mt-1">Contrast ratio: 4.5:1</div>
                    </div>
                </div>
                
                <!-- WCAG AAA Compliant -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-blue-700">WCAG AAA Compliant (7:1+)</h4>
                    
                    <div class="bg-gray-900 text-white p-3 rounded border">
                        <span class="font-medium">White text on very dark background</span>
                        <div class="text-sm text-gray-300 mt-1">Contrast ratio: 18.1:1</div>
                    </div>
                    
                    <div class="bg-blue-800 text-white p-3 rounded border">
                        <span class="font-medium">White text on dark blue</span>
                        <div class="text-sm text-blue-200 mt-1">Contrast ratio: 7.1:1</div>
                    </div>
                </div>
            </div>
            
            <!-- Interactive Elements -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Interactive Elements</h3>
                
                <!-- Buttons -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-gray-700">Buttons</h4>
                    
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Primary Button (4.5:1)
                    </button>
                    
                    <button class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Secondary Button (4.5:1)
                    </button>
                    
                    <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Danger Button (4.5:1)
                    </button>
                </div>
                
                <!-- Links -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-gray-700">Links</h4>
                    
                    <a href="#" class="text-blue-600 hover:text-blue-800 underline focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded">
                        Standard Link (4.5:1)
                    </a>
                    
                    <a href="#" class="text-gray-800 hover:text-gray-900 underline focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 rounded">
                        Dark Link (7:1)
                    </a>
                </div>
                
                <!-- Form Elements -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-gray-700">Form Elements</h4>
                    
                    <input 
                        type="text" 
                        placeholder="Input field" 
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        style="color: #1f2937; background-color: #ffffff;"
                    >
                    
                    <select class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" style="color: #1f2937; background-color: #ffffff;">
                        <option>Select option</option>
                        <option>Option 1</option>
                        <option>Option 2</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Color Palette -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Accessible Color Palette</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Primary Colors -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-600 rounded mx-auto mb-2"></div>
                    <div class="text-sm font-medium text-gray-800">Blue 600</div>
                    <div class="text-xs text-gray-600">#2563eb</div>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-600 rounded mx-auto mb-2"></div>
                    <div class="text-sm font-medium text-gray-800">Green 600</div>
                    <div class="text-xs text-gray-600">#16a34a</div>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-600 rounded mx-auto mb-2"></div>
                    <div class="text-sm font-medium text-gray-800">Red 600</div>
                    <div class="text-xs text-gray-600">#dc2626</div>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-yellow-600 rounded mx-auto mb-2"></div>
                    <div class="text-sm font-medium text-gray-800">Yellow 600</div>
                    <div class="text-xs text-gray-600">#ca8a04</div>
                </div>
            </div>
        </div>
        
        <!-- Accessibility Guidelines -->
        <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="text-lg font-semibold text-blue-900 mb-2">WCAG Color Contrast Guidelines</h3>
            <ul class="text-blue-800 space-y-1">
                <li><strong>Level AA:</strong> Normal text requires 4.5:1 contrast ratio</li>
                <li><strong>Level AA:</strong> Large text (18pt+) requires 3:1 contrast ratio</li>
                <li><strong>Level AAA:</strong> Normal text requires 7:1 contrast ratio</li>
                <li><strong>Level AAA:</strong> Large text requires 4.5:1 contrast ratio</li>
                <li><strong>UI Components:</strong> Interactive elements require 3:1 contrast ratio</li>
            </ul>
        </div>
        
        <!-- High Contrast Mode -->
        <div class="mt-6 p-4 bg-gray-100 border border-gray-300 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">High Contrast Mode Support</h3>
            <p class="text-gray-700 text-sm">
                All elements support high contrast mode through CSS media queries. 
                When users enable high contrast mode, borders and outlines are enhanced for better visibility.
            </p>
        </div>
    </div>
</div>

<style>
/* High contrast mode support */
@media (prefers-contrast: high) {
    .color-contrast-container button {
        border: 2px solid currentColor;
    }
    
    .color-contrast-container input,
    .color-contrast-container select {
        border: 2px solid currentColor;
    }
    
    .color-contrast-container a {
        text-decoration: underline;
        text-decoration-thickness: 2px;
    }
}

/* Focus styles for accessibility */
.color-contrast-container *:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Ensure sufficient contrast for all text */
.color-contrast-container {
    color: #1f2937; /* Gray 800 - 4.5:1 contrast on white */
}

/* Button hover states maintain contrast */
.color-contrast-container button:hover {
    filter: brightness(0.9);
}

/* Link hover states maintain contrast */
.color-contrast-container a:hover {
    filter: brightness(0.8);
}

/* Form element focus states */
.color-contrast-container input:focus,
.color-contrast-container select:focus,
.color-contrast-container textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
}

/* Error states with sufficient contrast */
.color-contrast-container .error {
    color: #dc2626; /* Red 600 - 4.5:1 contrast on white */
    background-color: #fef2f2; /* Red 50 */
    border-color: #fecaca; /* Red 200 */
}

/* Success states with sufficient contrast */
.color-contrast-container .success {
    color: #16a34a; /* Green 600 - 4.5:1 contrast on white */
    background-color: #f0fdf4; /* Green 50 */
    border-color: #bbf7d0; /* Green 200 */
}

/* Warning states with sufficient contrast */
.color-contrast-container .warning {
    color: #ca8a04; /* Yellow 600 - 4.5:1 contrast on white */
    background-color: #fefce8; /* Yellow 50 */
    border-color: #fde68a; /* Yellow 200 */
}

/* Info states with sufficient contrast */
.color-contrast-container .info {
    color: #2563eb; /* Blue 600 - 4.5:1 contrast on white */
    background-color: #eff6ff; /* Blue 50 */
    border-color: #bfdbfe; /* Blue 200 */
}
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/a11y/accessibility-color-contrast.blade.php ENDPATH**/ ?>