{{-- Accessibility Test Page --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessibility Test - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Screen reader only class */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        /* Focus styles */
        *:focus {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            *:focus {
                outline: 3px solid #000000;
                outline-offset: 2px;
            }
            
            button {
                border: 2px solid currentColor;
            }
            
            input, select, textarea {
                border: 2px solid currentColor;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50" x-data="accessibilityTest()">
    <!-- Skip Links -->
    <div class="skip-links">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 focus:z-50 focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">
            Skip to main content
        </a>
        <a href="#navigation" class="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 focus:z-50 focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">
            Skip to navigation
        </a>
        <a href="#search" class="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 focus:z-50 focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">
            Skip to search
        </a>
    </div>
    
    <!-- Header -->
    <header role="banner" aria-label="Site header" class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">ZenaManage</h1>
                    <span class="ml-2 text-sm text-gray-500">Accessibility Test</span>
                </div>
                <div class="flex items-center space-x-4">
                    <button 
                        @click="toggleTheme()"
                        aria-label="Toggle theme"
                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <i class="fas fa-moon" x-show="!isDarkMode"></i>
                        <i class="fas fa-sun" x-show="isDarkMode"></i>
                    </button>
                    <button 
                        @click="toggleHighContrast()"
                        aria-label="Toggle high contrast mode"
                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <i class="fas fa-adjust"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Navigation -->
    <nav role="navigation" aria-label="Main navigation" id="navigation" class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8 py-2">
                <a href="#" class="text-blue-600 border-b-2 border-blue-600 px-1 py-2 text-sm font-medium" aria-current="page">
                    Accessibility Test
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Keyboard Navigation
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Screen Reader
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Color Contrast
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main role="main" id="main-content" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Title -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Accessibility Test Page</h1>
            <p class="text-lg text-gray-600">
                This page demonstrates WCAG 2.1 AA compliance features including keyboard navigation, 
                screen reader support, focus management, and color contrast.
            </p>
        </div>
        
        <!-- Keyboard Shortcuts -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-blue-900 mb-4">Keyboard Shortcuts</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-lg font-medium text-blue-800 mb-2">Navigation</h3>
                    <ul class="space-y-1 text-blue-700">
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Tab</kbd> Navigate forward</li>
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Shift + Tab</kbd> Navigate backward</li>
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Enter</kbd> Activate button/link</li>
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Space</kbd> Activate button</li>
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Escape</kbd> Close modal/menu</li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-medium text-blue-800 mb-2">Quick Access</h3>
                    <ul class="space-y-1 text-blue-700">
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Alt + S</kbd> Focus search</li>
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Alt + N</kbd> Focus navigation</li>
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Alt + M</kbd> Open modal</li>
                        <li><kbd class="px-2 py-1 bg-blue-100 rounded text-sm">Alt + H</kbd> Show help</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Interactive Elements Test -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Form Elements -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Form Elements</h2>
                
                <form class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            Full Name <span class="text-red-500" aria-label="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required
                            aria-describedby="name-help"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                        <p id="name-help" class="text-sm text-gray-500 mt-1">Enter your full name as it appears on official documents</p>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                            Email Address <span class="text-red-500" aria-label="required">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            aria-describedby="email-help"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                        <p id="email-help" class="text-sm text-gray-500 mt-1">We'll use this to send you important updates</p>
                    </div>
                    
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select 
                            id="role" 
                            name="role"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">Select a role</option>
                            <option value="admin">Administrator</option>
                            <option value="manager">Project Manager</option>
                            <option value="member">Team Member</option>
                            <option value="client">Client</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="bio" class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                        <textarea 
                            id="bio" 
                            name="bio" 
                            rows="3"
                            aria-describedby="bio-help"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        ></textarea>
                        <p id="bio-help" class="text-sm text-gray-500 mt-1">Tell us about yourself (optional)</p>
                    </div>
                    
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="newsletter" 
                            name="newsletter"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <label for="newsletter" class="ml-2 block text-sm text-gray-700">
                            Subscribe to newsletter
                        </label>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button 
                            type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Save Changes
                        </button>
                        <button 
                            type="button" 
                            @click="resetForm()"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Interactive Components -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Interactive Components</h2>
                
                <!-- Buttons -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">Buttons</h3>
                    <div class="flex flex-wrap gap-3">
                        <button 
                            @click="showAlert('Primary action')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Primary Action
                        </button>
                        <button 
                            @click="showAlert('Secondary action')"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                        >
                            Secondary Action
                        </button>
                        <button 
                            @click="showAlert('Danger action')"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            Danger Action
                        </button>
                    </div>
                </div>
                
                <!-- Modal Test -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">Modal Test</h3>
                    <button 
                        @click="openModal()"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                    >
                        Open Modal
                    </button>
                </div>
                
                <!-- Progress Bar -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">Progress Bar</h3>
                    <div 
                        role="progressbar" 
                        :aria-valuenow="progressValue" 
                        aria-valuemin="0" 
                        aria-valuemax="100" 
                        aria-label="Progress"
                        class="w-full bg-gray-200 rounded-full h-2"
                    >
                        <div 
                            class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                            :style="`width: ${progressValue}%`"
                        ></div>
                    </div>
                    <div class="flex justify-between mt-2">
                        <span class="text-sm text-gray-600">0%</span>
                        <span class="text-sm text-gray-600" x-text="`${progressValue}%`"></span>
                        <span class="text-sm text-gray-600">100%</span>
                    </div>
                    <button 
                        @click="updateProgress()"
                        class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Update Progress
                    </button>
                </div>
                
                <!-- Tabs -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">Tabs</h3>
                    <div role="tablist" aria-label="Tab navigation">
                        <div class="flex border-b border-gray-200">
                            <button 
                                role="tab" 
                                :aria-selected="activeTab === 'tab1'" 
                                aria-controls="tab-panel-1" 
                                id="tab-1"
                                @click="activeTab = 'tab1'"
                                :class="activeTab === 'tab1' ? 'bg-blue-50 text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 border-b-2 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Tab 1
                            </button>
                            <button 
                                role="tab" 
                                :aria-selected="activeTab === 'tab2'" 
                                aria-controls="tab-panel-2" 
                                id="tab-2"
                                @click="activeTab = 'tab2'"
                                :class="activeTab === 'tab2' ? 'bg-blue-50 text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 border-b-2 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Tab 2
                            </button>
                        </div>
                        
                        <div 
                            role="tabpanel" 
                            aria-labelledby="tab-1" 
                            id="tab-panel-1"
                            x-show="activeTab === 'tab1'"
                            class="p-4 bg-gray-50"
                        >
                            <p class="text-gray-700">Content for Tab 1</p>
                        </div>
                        
                        <div 
                            role="tabpanel" 
                            aria-labelledby="tab-2" 
                            id="tab-panel-2"
                            x-show="activeTab === 'tab2'"
                            class="p-4 bg-gray-50"
                        >
                            <p class="text-gray-700">Content for Tab 2</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Color Contrast Test -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Color Contrast Test</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- WCAG AA Compliant -->
                <div>
                    <h3 class="text-lg font-medium text-green-700 mb-3">WCAG AA Compliant (4.5:1+)</h3>
                    <div class="space-y-2">
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
                    </div>
                </div>
                
                <!-- WCAG AAA Compliant -->
                <div>
                    <h3 class="text-lg font-medium text-blue-700 mb-3">WCAG AAA Compliant (7:1+)</h3>
                    <div class="space-y-2">
                        <div class="bg-gray-900 text-white p-3 rounded border">
                            <span class="font-medium">White text on very dark background</span>
                            <div class="text-sm text-gray-300 mt-1">Contrast ratio: 18.1:1</div>
                        </div>
                        <div class="bg-blue-800 text-white p-3 rounded border">
                            <span class="font-medium">White text on dark blue</span>
                            <div class="text-sm text-blue-200 mt-1">Contrast ratio: 7.1:1</div>
                        </div>
                        <div class="bg-green-800 text-white p-3 rounded border">
                            <span class="font-medium">White text on dark green</span>
                            <div class="text-sm text-green-200 mt-1">Contrast ratio: 7.1:1</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Live Region Test -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Live Region Test</h2>
            
            <div class="space-y-4">
                <div class="flex space-x-3">
                    <button 
                        @click="announceMessage('This is a polite announcement')"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Polite Announcement
                    </button>
                    <button 
                        @click="announceMessage('This is an assertive announcement', 'assertive')"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                    >
                        Assertive Announcement
                    </button>
                </div>
                
                <!-- Live region -->
                <div 
                    aria-live="polite" 
                    aria-atomic="true" 
                    class="sr-only"
                    x-ref="liveRegion"
                ></div>
                
                <div class="text-sm text-gray-600">
                    <p>Click the buttons above to test live region announcements. Screen readers will announce the messages.</p>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Modal -->
    <div 
        x-show="isModalOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        aria-describedby="modal-description"
        @keydown.escape="closeModal()"
        @keydown.tab="handleTabKey($event)"
    >
        <div class="flex min-h-full items-center justify-center p-4">
            <div 
                class="relative bg-white rounded-lg shadow-xl max-w-md w-full"
                @click.stop
                x-ref="modalContent"
            >
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 id="modal-title" class="text-lg font-semibold text-gray-900">
                        Accessibility Modal Test
                    </h2>
                </div>
                
                <div class="px-6 py-4">
                    <p id="modal-description" class="text-gray-600 mb-4">
                        This modal demonstrates proper focus management and keyboard navigation.
                        Press Tab to navigate, Escape to close.
                    </p>
                    <div class="space-y-3">
                        <input 
                            type="text" 
                            placeholder="Test input"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option>Test select</option>
                            <option>Option 1</option>
                            <option>Option 2</option>
                        </select>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button 
                        @click="closeModal()"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                        ref="cancelButton"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="confirmModal()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        ref="confirmButton"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer role="contentinfo" aria-label="Site footer" class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center text-gray-600">
                <p>&copy; 2025 ZenaManage. All rights reserved.</p>
                <p class="mt-2 text-sm">This page demonstrates WCAG 2.1 AA accessibility compliance.</p>
            </div>
        </div>
    </footer>
    
    <script>
        function accessibilityTest() {
            return {
                isDarkMode: false,
                isHighContrast: false,
                isModalOpen: false,
                activeTab: 'tab1',
                progressValue: 0,
                focusableElements: [],
                
                init() {
                    // Set up keyboard shortcuts
                    document.addEventListener('keydown', this.handleGlobalKeydown.bind(this));
                },
                
                toggleTheme() {
                    this.isDarkMode = !this.isDarkMode;
                    document.body.classList.toggle('dark', this.isDarkMode);
                },
                
                toggleHighContrast() {
                    this.isHighContrast = !this.isHighContrast;
                    document.body.classList.toggle('high-contrast', this.isHighContrast);
                },
                
                openModal() {
                    this.isModalOpen = true;
                    this.$nextTick(() => {
                        this.focusableElements = this.getFocusableElements(this.$refs.modalContent);
                        if (this.focusableElements.length > 0) {
                            this.focusableElements[0].focus();
                        }
                    });
                },
                
                closeModal() {
                    this.isModalOpen = false;
                },
                
                confirmModal() {
                    this.announceMessage('Modal confirmed');
                    this.closeModal();
                },
                
                getFocusableElements(container) {
                    const focusableSelectors = [
                        'button:not([disabled])',
                        'input:not([disabled])',
                        'select:not([disabled])',
                        'textarea:not([disabled])',
                        'a[href]',
                        '[tabindex]:not([tabindex="-1"])'
                    ];
                    return container.querySelectorAll(focusableSelectors.join(', '));
                },
                
                handleTabKey(event) {
                    if (!this.isModalOpen) return;
                    
                    const firstElement = this.focusableElements[0];
                    const lastElement = this.focusableElements[this.focusableElements.length - 1];
                    
                    if (event.shiftKey) {
                        if (document.activeElement === firstElement) {
                            event.preventDefault();
                            lastElement.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            event.preventDefault();
                            firstElement.focus();
                        }
                    }
                },
                
                handleGlobalKeydown(event) {
                    // Alt + M to open modal
                    if (event.altKey && event.key === 'm') {
                        event.preventDefault();
                        this.openModal();
                    }
                    
                    // Alt + S to focus search
                    if (event.altKey && event.key === 's') {
                        event.preventDefault();
                        const searchInput = document.querySelector('[data-search-input]');
                        if (searchInput) {
                            searchInput.focus();
                        }
                    }
                    
                    // Alt + N to focus navigation
                    if (event.altKey && event.key === 'n') {
                        event.preventDefault();
                        const navElement = document.querySelector('[role="navigation"]');
                        if (navElement) {
                            navElement.focus();
                        }
                    }
                },
                
                showAlert(message) {
                    alert(message);
                },
                
                resetForm() {
                    document.querySelector('form').reset();
                    this.announceMessage('Form reset');
                },
                
                updateProgress() {
                    this.progressValue = Math.min(100, this.progressValue + 25);
                    this.announceMessage(`Progress updated to ${this.progressValue}%`);
                },
                
                announceMessage(message, politeness = 'polite') {
                    if (this.$refs.liveRegion) {
                        this.$refs.liveRegion.setAttribute('aria-live', politeness);
                        this.$refs.liveRegion.textContent = message;
                        
                        setTimeout(() => {
                            this.$refs.liveRegion.textContent = '';
                        }, 1000);
                    }
                }
            }
        }
    </script>
</body>
</html>
