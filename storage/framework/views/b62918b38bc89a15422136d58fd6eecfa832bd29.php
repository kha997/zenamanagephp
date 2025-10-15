


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Component Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
</head>
<body class="bg-gray-50">
    
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4 px-4">App Header Component</h2>
        <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.header-standardized','data' => ['variant' => 'app','notifications' => [
                ['message' => 'New project created', 'icon' => 'project-diagram', 'color' => 'blue', 'time' => '2m ago'],
                ['message' => 'Task completed', 'icon' => 'check-circle', 'color' => 'green', 'time' => '5m ago'],
                ['message' => 'Team member joined', 'icon' => 'user-plus', 'color' => 'purple', 'time' => '1h ago']
            ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.header-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'app','notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
                ['message' => 'New project created', 'icon' => 'project-diagram', 'color' => 'blue', 'time' => '2m ago'],
                ['message' => 'Task completed', 'icon' => 'check-circle', 'color' => 'green', 'time' => '5m ago'],
                ['message' => 'Team member joined', 'icon' => 'user-plus', 'color' => 'purple', 'time' => '1h ago']
            ])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">App Header Features</h3>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li>✅ Responsive navigation with mobile menu</li>
                    <li>✅ Notifications dropdown with unread count</li>
                    <li>✅ User menu with profile and settings</li>
                    <li>✅ Condensed header on scroll</li>
                    <li>✅ Proper accessibility attributes</li>
                    <li>✅ Dark/light theme support</li>
                </ul>
            </div>
        </div>
    </div>

    
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4 px-4">Admin Header Component</h2>
        <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.header-standardized','data' => ['variant' => 'admin','notifications' => [
                ['message' => 'New tenant registered', 'icon' => 'building', 'color' => 'blue', 'time' => '10m ago'],
                ['message' => 'System backup completed', 'icon' => 'save', 'color' => 'green', 'time' => '1h ago'],
                ['message' => 'Security alert', 'icon' => 'shield-alt', 'color' => 'red', 'time' => '2h ago']
            ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.header-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'admin','notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
                ['message' => 'New tenant registered', 'icon' => 'building', 'color' => 'blue', 'time' => '10m ago'],
                ['message' => 'System backup completed', 'icon' => 'save', 'color' => 'green', 'time' => '1h ago'],
                ['message' => 'Security alert', 'icon' => 'shield-alt', 'color' => 'red', 'time' => '2h ago']
            ])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Admin Header Features</h3>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li>✅ Admin-specific navigation items</li>
                    <li>✅ System notifications</li>
                    <li>✅ Admin branding</li>
                    <li>✅ Same responsive behavior as app header</li>
                    <li>✅ Consistent user experience</li>
                </ul>
            </div>
        </div>
    </div>

    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Theme Toggle</h3>
            <div class="flex items-center space-x-4">
                <button onclick="toggleTheme('light')" 
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                    Light Theme
                </button>
                <button onclick="toggleTheme('dark')" 
                        class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700">
                    Dark Theme
                </button>
            </div>
            <p class="text-sm text-gray-600 mt-4">
                Toggle between light and dark themes to see how the header adapts.
            </p>
        </div>
    </div>

    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Component Props</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prop</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">variant</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">string</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">'app'</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Header variant: 'app' or 'admin'</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">user</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">User|null</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Auth::user()</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Current authenticated user</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">tenant</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Tenant|null</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">user.tenant</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Current tenant context</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">notifications</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">array</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">[]</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Array of notification objects</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">showNotifications</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">boolean</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">true</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Show/hide notifications dropdown</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">showUserMenu</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">boolean</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">true</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Show/hide user menu dropdown</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">customActions</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">slot</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">null</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Custom action buttons slot</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">theme</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">string</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">'light'</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Theme variant: 'light' or 'dark'</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Usage Examples</h3>
            
            <div class="space-y-4">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Basic App Header</h4>
                    <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto"><code>&lt;x-shared.header-standardized variant="app" /&gt;</code></pre>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Admin Header with Notifications</h4>
                    <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto"><code>&lt;x-shared.header-standardized 
    variant="admin"
    :notifications="$notifications" /&gt;</code></pre>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Header with Custom Actions</h4>
                    <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto"><code>&lt;x-shared.header-standardized variant="app"&gt;
    &lt;x-slot name="customActions"&gt;
        &lt;button class="btn-primary"&gt;Custom Action&lt;/button&gt;
    &lt;/x-slot&gt;
&lt;/x-shared.header-standardized&gt;</code></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
        }
        
        // Add some scroll content to test condensed header
        document.body.style.height = '200vh';
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_demos/header-demo.blade.php ENDPATH**/ ?>