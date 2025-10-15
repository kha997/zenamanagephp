{{-- Comprehensive Component Demo Page --}}
{{-- Showcases all standardized components --}}

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Component Library Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-gray-50" x-data="demoApp()">
    
    {{-- Header Demo --}}
    <section class="mb-8">
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-2xl font-bold mb-4">Header Components</h2>
            <p class="text-gray-600 mb-4">Standardized header with responsive navigation and notifications.</p>
            
            <div class="space-y-4">
                <div>
                    <h3 class="text-lg font-semibold mb-2">App Header</h3>
                    <x-shared.header-standardized 
                        variant="app"
                        :notifications="[
                            ['message' => 'New project created', 'icon' => 'project-diagram', 'color' => 'blue', 'time' => '2m ago'],
                            ['message' => 'Task completed', 'icon' => 'check-circle', 'color' => 'green', 'time' => '5m ago']
                        ]" />
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-2">Admin Header</h3>
                    <x-shared.header-standardized 
                        variant="admin"
                        :notifications="[
                            ['message' => 'New tenant registered', 'icon' => 'building', 'color' => 'blue', 'time' => '10m ago'],
                            ['message' => 'System backup completed', 'icon' => 'save', 'color' => 'green', 'time' => '1h ago']
                        ]" />
                </div>
            </div>
        </div>
    </section>
    
    {{-- Layout Demo --}}
    <section class="mb-8">
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-2xl font-bold mb-4">Layout Components</h2>
            <p class="text-gray-600 mb-4">Consistent layout structure with breadcrumbs and actions.</p>
            
            <x-shared.layout-wrapper 
                title="Sample Page"
                subtitle="This is a demo of the layout wrapper component"
                :breadcrumbs="[
                    ['label' => 'Home', 'url' => '#'],
                    ['label' => 'Components', 'url' => '#'],
                    ['label' => 'Layout', 'url' => null]
                ]"
                :actions="'<button class=\"btn bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700\">Action Button</button>'">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <x-shared.card-standardized title="Card 1" subtitle="Sample card content">
                        <p class="text-gray-600">This is a standardized card component with consistent styling.</p>
                    </x-shared.card-standardized>
                    
                    <x-shared.card-standardized title="Card 2" variant="bordered">
                        <p class="text-gray-600">This card has a bordered variant for emphasis.</p>
                    </x-shared.card-standardized>
                    
                    <x-shared.card-standardized title="Card 3" variant="elevated" hover="true">
                        <p class="text-gray-600">This card has elevation and hover effects.</p>
                    </x-shared.card-standardized>
                </div>
            </x-shared.layout-wrapper>
        </div>
    </section>
    
    {{-- Data Display Demo --}}
    <section class="mb-8">
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-2xl font-bold mb-4">Data Display Components</h2>
            <p class="text-gray-600 mb-4">Tables, cards, and forms with consistent styling.</p>
            
            {{-- Table Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Standardized Table</h3>
                <x-shared.table-standardized 
                    title="Sample Data"
                    subtitle="Demonstrating table functionality"
                    :columns="[
                        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                        ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                        ['key' => 'status', 'label' => 'Status', 'format' => 'status', 'status_config' => ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-red-100 text-red-800']],
                        ['key' => 'created_at', 'label' => 'Created', 'format' => 'date', 'sortable' => true]
                    ]"
                    :items="[
                        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'created_at' => '2024-01-15'],
                        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'inactive', 'created_at' => '2024-01-14'],
                        ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'status' => 'active', 'created_at' => '2024-01-13']
                    ]"
                    :actions="[
                        ['type' => 'link', 'icon' => 'fas fa-eye', 'label' => 'View', 'url' => fn($item) => '#', 'title' => 'View details'],
                        ['type' => 'button', 'icon' => 'fas fa-edit', 'label' => 'Edit', 'handler' => 'editItem', 'title' => 'Edit item']
                    ]"
                    show-bulk-actions="true"
                    show-search="true" />
            </div>
            
            {{-- Form Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Form Components</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-shared.form-input 
                            name="name"
                            label="Full Name"
                            placeholder="Enter your name"
                            required="true"
                            icon="fas fa-user"
                            help="This is a help text for the input field" />
                        
                        <x-shared.form-input 
                            name="email"
                            label="Email Address"
                            type="email"
                            placeholder="Enter your email"
                            required="true"
                            icon="fas fa-envelope"
                            icon-position="right" />
                    </div>
                    
                    <div>
                        <x-shared.form-input 
                            name="phone"
                            label="Phone Number"
                            placeholder="Enter your phone"
                            icon="fas fa-phone" />
                        
                        <x-shared.form-input 
                            name="password"
                            label="Password"
                            type="password"
                            placeholder="Enter your password"
                            required="true"
                            icon="fas fa-lock" />
                    </div>
                </div>
            </div>
            
            {{-- Button Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Button Components</h3>
                <div class="flex flex-wrap gap-4">
                    <x-shared.button-standardized variant="primary" icon="fas fa-save">Save</x-shared.button-standardized>
                    <x-shared.button-standardized variant="secondary" icon="fas fa-cancel">Cancel</x-shared.button-standardized>
                    <x-shared.button-standardized variant="success" icon="fas fa-check">Success</x-shared.button-standardized>
                    <x-shared.button-standardized variant="danger" icon="fas fa-trash">Delete</x-shared.button-standardized>
                    <x-shared.button-standardized variant="ghost" icon="fas fa-edit">Edit</x-shared.button-standardized>
                    <x-shared.button-standardized variant="link" icon="fas fa-external-link-alt">Link</x-shared.button-standardized>
                </div>
                
                <div class="mt-4">
                    <h4 class="font-medium mb-2">Button Sizes</h4>
                    <div class="flex items-center gap-4">
                        <x-shared.button-standardized size="xs" variant="primary">Extra Small</x-shared.button-standardized>
                        <x-shared.button-standardized size="sm" variant="primary">Small</x-shared.button-standardized>
                        <x-shared.button-standardized size="md" variant="primary">Medium</x-shared.button-standardized>
                        <x-shared.button-standardized size="lg" variant="primary">Large</x-shared.button-standardized>
                        <x-shared.button-standardized size="xl" variant="primary">Extra Large</x-shared.button-standardized>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    {{-- Feedback Components Demo --}}
    <section class="mb-8">
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-2xl font-bold mb-4">Feedback Components</h2>
            <p class="text-gray-600 mb-4">Alerts, notifications, and empty states.</p>
            
            {{-- Alert Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Alert Components</h3>
                <div class="space-y-4">
                    <x-shared.alert-standardized 
                        type="success"
                        title="Success!"
                        message="Your changes have been saved successfully."
                        dismissible="true" />
                    
                    <x-shared.alert-standardized 
                        type="warning"
                        title="Warning"
                        message="Please review your input before proceeding."
                        dismissible="true" />
                    
                    <x-shared.alert-standardized 
                        type="error"
                        title="Error"
                        message="Something went wrong. Please try again."
                        dismissible="true" />
                    
                    <x-shared.alert-standardized 
                        type="info"
                        title="Information"
                        message="This is an informational message."
                        dismissible="true" />
                </div>
            </div>
            
            {{-- Empty State Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Empty State Components</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-shared.empty-state 
                        icon="fas fa-inbox"
                        title="No items found"
                        description="There are no items to display at the moment."
                        action-text="Create Item"
                        action-icon="fas fa-plus"
                        action-handler="createItem" />
                    
                    <x-shared.empty-state 
                        icon="fas fa-search"
                        title="No search results"
                        description="Try adjusting your search criteria."
                        variant="illustrated" />
                </div>
            </div>
        </div>
    </section>
    
    {{-- Mobile Components Demo --}}
    <section class="mb-8">
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-2xl font-bold mb-4">Mobile Components</h2>
            <p class="text-gray-600 mb-4">Mobile-first components for responsive design.</p>
            
            {{-- Hamburger Menu Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Hamburger Menu</h3>
                <div class="flex items-center gap-4">
                    <x-shared.hamburger-menu size="sm" />
                    <x-shared.hamburger-menu size="md" />
                    <x-shared.hamburger-menu size="lg" />
                </div>
            </div>
            
            {{-- FAB Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Floating Action Button</h3>
                <div class="relative h-32 bg-gray-100 rounded-lg">
                    <x-shared.fab 
                        icon="fas fa-plus"
                        label="Add Item"
                        position="bottom-right"
                        size="md"
                        variant="primary" />
                </div>
            </div>
            
            {{-- Mobile Sheet Demo --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Mobile Sheet</h3>
                <button @click="showSheet = true" 
                        class="btn bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Open Mobile Sheet
                </button>
                
                <x-shared.mobile-sheet 
                    title="Mobile Sheet Demo"
                    :actions="'<button class=\"btn bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700\">Action</button>'"
                    x-model:open="showSheet">
                    
                    <div class="space-y-4">
                        <p class="text-gray-600">This is a mobile sheet component that slides up from the bottom.</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-100 rounded-lg">
                                <h4 class="font-medium">Option 1</h4>
                                <p class="text-sm text-gray-600">Description</p>
                            </div>
                            <div class="p-4 bg-gray-100 rounded-lg">
                                <h4 class="font-medium">Option 2</h4>
                                <p class="text-sm text-gray-600">Description</p>
                            </div>
                        </div>
                    </div>
                </x-shared.mobile-sheet>
            </div>
        </div>
    </section>
    
    {{-- Theme Toggle --}}
    <div class="fixed bottom-4 left-4 z-50">
        <button @click="toggleTheme()" 
                class="btn bg-gray-800 text-white px-4 py-2 rounded-full shadow-lg hover:bg-gray-700">
            <i class="fas fa-moon mr-2"></i>
            Toggle Theme
        </button>
    </div>
    
    <script>
        function demoApp() {
            return {
                showSheet: false,
                theme: 'light',
                
                init() {
                    this.theme = document.documentElement.getAttribute('data-theme') || 'light';
                },
                
                toggleTheme() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    document.documentElement.setAttribute('data-theme', this.theme);
                },
                
                editItem(id) {
                    alert(`Edit item ${id}`);
                },
                
                createItem() {
                    alert('Create new item');
                }
            }
        }
    </script>
</body>
</html>
