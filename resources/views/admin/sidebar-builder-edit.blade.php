<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sidebar - {{ ucwords(str_replace('_', ' ', $role)) }} - ZENA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar-item {
            transition: all 0.2s ease;
        }
        .sidebar-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .drag-handle {
            cursor: grab;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .sortable-ghost {
            opacity: 0.4;
        }
        .sortable-chosen {
            transform: rotate(5deg);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <a href="{{ route('admin.sidebar-builder') }}" class="text-gray-500 hover:text-gray-700 mr-4">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <h1 class="text-2xl font-bold text-gray-900">
                            Edit Sidebar - {{ ucwords(str_replace('_', ' ', $role)) }}
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('admin.sidebar-builder.preview', $role) }}" 
                           class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200 transition-colors">
                            <i class="fas fa-eye mr-2"></i>
                            Preview
                        </a>
                        <button onclick="saveConfig()" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Sidebar Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border">
                        <div class="p-6 border-b">
                            <h2 class="text-lg font-semibold text-gray-900">Sidebar Items</h2>
                            <p class="text-sm text-gray-500 mt-1">Drag and drop to reorder items</p>
                        </div>
                        
                        <div id="sidebar-items" class="p-6 space-y-4">
                            @if(isset($config->config['items']))
                                @foreach($config->config['items'] as $index => $item)
                                    <div class="sidebar-item bg-gray-50 rounded-lg p-4 border" data-item-id="{{ $item['id'] }}">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="drag-handle text-gray-400 hover:text-gray-600">
                                                    <i class="fas fa-grip-vertical"></i>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    @if(isset($item['icon']))
                                                        <i class="fas fa-{{ $item['icon'] }} text-gray-500"></i>
                                                    @endif
                                                    <span class="font-medium">{{ $item['label'] }}</span>
                                                    <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded">
                                                        {{ $item['type'] }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <label class="flex items-center">
                                                    <input type="checkbox" 
                                                           {{ ($item['enabled'] ?? true) ? 'checked' : '' }}
                                                           onchange="toggleItem('{{ $item['id'] }}', this.checked)"
                                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="ml-2 text-sm text-gray-600">Enabled</span>
                                                </label>
                                                <button onclick="editItem('{{ $item['id'] }}')" 
                                                        class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteItem('{{ $item['id'] }}')" 
                                                        class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        @if($item['type'] === 'group' && isset($item['children']))
                                            <div class="mt-3 ml-8 space-y-2">
                                                @foreach($item['children'] as $childIndex => $child)
                                                    <div class="flex items-center justify-between bg-white rounded p-2 border">
                                                        <div class="flex items-center space-x-2">
                                                            @if(isset($child['icon']))
                                                                <i class="fas fa-{{ $child['icon'] }} text-gray-400"></i>
                                                            @endif
                                                            <span class="text-sm">{{ $child['label'] }}</span>
                                                        </div>
                                                        <div class="flex items-center space-x-2">
                                                            <label class="flex items-center">
                                                                <input type="checkbox" 
                                                                       {{ ($child['enabled'] ?? true) ? 'checked' : '' }}
                                                                       onchange="toggleChildItem('{{ $item['id'] }}', '{{ $child['id'] }}', this.checked)"
                                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                <span class="ml-1 text-xs text-gray-500">Enabled</span>
                                                            </label>
                                                            <button onclick="editChildItem('{{ $item['id'] }}', '{{ $child['id'] }}')" 
                                                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        
                        <div class="p-6 border-t">
                            <button onclick="addNewItem()" 
                                    class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Add New Item
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tools & Actions -->
                <div class="space-y-6">
                    <!-- Role Info -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Role Information</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Role:</span>
                                <span class="font-medium">{{ ucwords(str_replace('_', ' ', $role)) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="text-sm {{ isset($config->is_default) ? 'text-gray-500' : 'text-green-600' }}">
                                    {{ isset($config->is_default) ? 'Using Default' : 'Customized' }}
                                </span>
                            </div>
                            @if(!isset($config->is_default))
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Version:</span>
                                    <span class="font-medium">{{ $config->version ?? 1 }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <button onclick="cloneFromRole()" 
                                    class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors">
                                <i class="fas fa-copy mr-2"></i>
                                Clone from Another Role
                            </button>
                            
                            <button onclick="resetToDefault()" 
                                    class="w-full bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition-colors">
                                <i class="fas fa-undo mr-2"></i>
                                Reset to Default
                            </button>
                            
                            <button onclick="exportConfig()" 
                                    class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                                <i class="fas fa-download mr-2"></i>
                                Export Config
                            </button>
                            
                            <button onclick="importConfig()" 
                                    class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors">
                                <i class="fas fa-upload mr-2"></i>
                                Import Config
                            </button>
                            
                            <button onclick="showPresets()" 
                                    class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-magic mr-2"></i>
                                Apply Preset
                            </button>
                        </div>
                    </div>

                    <!-- Help -->
                    <div class="bg-blue-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-blue-900 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>
                            Tips
                        </h3>
                        <div class="text-blue-800 text-sm space-y-1">
                            <p>• Drag items to reorder</p>
                            <p>• Click edit to modify item properties</p>
                            <p>• Use preview to see changes</p>
                            <p>• Changes are auto-saved</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize sortable
        document.addEventListener('DOMContentLoaded', function() {
            const sortable = Sortable.create(document.getElementById('sidebar-items'), {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function(evt) {
                    updateItemOrder();
                }
            });
        });

        function toggleItem(itemId, enabled) {
            // TODO: Implement toggle functionality
            console.log('Toggle item:', itemId, enabled);
        }

        function toggleChildItem(parentId, childId, enabled) {
            // TODO: Implement toggle child item functionality
            console.log('Toggle child item:', parentId, childId, enabled);
        }

        function editItem(itemId) {
            // TODO: Implement edit item functionality
            alert('Edit item functionality will be implemented in the next phase');
        }

        function editChildItem(parentId, childId) {
            // TODO: Implement edit child item functionality
            alert('Edit child item functionality will be implemented in the next phase');
        }

        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item?')) {
                // TODO: Implement delete functionality
                console.log('Delete item:', itemId);
            }
        }

        function addNewItem() {
            // TODO: Implement add new item functionality
            alert('Add new item functionality will be implemented in the next phase');
        }

        function updateItemOrder() {
            // TODO: Implement update order functionality
            console.log('Update item order');
        }

        function saveConfig() {
            // TODO: Implement save functionality
            alert('Save functionality will be implemented in the next phase');
        }

        function cloneFromRole() {
            // Show clone modal
            const modal = document.getElementById('cloneModal');
            if (modal) {
                modal.style.display = 'block';
            } else {
                // Create modal dynamically
                createCloneModal();
            }
        }

        function createCloneModal() {
            const modal = document.createElement('div');
            modal.id = 'cloneModal';
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Clone Configuration</h3>
                        <form id="cloneForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">From Role:</label>
                                <select name="from_role" id="from_role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="">Select source role...</option>
                                    <option value="super_admin">Super Admin</option>
                                    <option value="admin">Admin</option>
                                    <option value="project_manager">Project Manager</option>
                                    <option value="designer">Designer</option>
                                    <option value="site_engineer">Site Engineer</option>
                                    <option value="qc">QC</option>
                                    <option value="procurement">Procurement</option>
                                    <option value="finance">Finance</option>
                                    <option value="client">Client</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">To Role:</label>
                                <input type="text" name="to_role" id="to_role" value="{{ $role }}" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeCloneModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Clone
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Handle form submission
            document.getElementById('cloneForm').addEventListener('submit', function(e) {
                e.preventDefault();
                performClone();
            });
        }

        function closeCloneModal() {
            const modal = document.getElementById('cloneModal');
            if (modal) {
                modal.remove();
            }
        }

        function performClone() {
            const formData = new FormData(document.getElementById('cloneForm'));
            const data = {
                from_role: formData.get('from_role'),
                to_role: formData.get('to_role'),
                tenant_id: null
            };

            fetch('{{ route("admin.sidebar-builder.clone") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuration cloned successfully!');
                    closeCloneModal();
                    location.reload(); // Reload to show updated config
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while cloning configuration');
            });
        }

        function resetToDefault() {
            if (confirm('Are you sure you want to reset to default? This will lose all customizations.')) {
                fetch('{{ route("admin.sidebar-builder.reset", $role) }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        tenant_id: null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Configuration reset to default successfully!');
                        location.reload(); // Reload to show default config
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while resetting configuration');
                });
            }
        }

        function exportConfig() {
            fetch('{{ route("admin.sidebar-builder.export", $role) }}', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create download link
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `sidebar-config-{{ $role }}-${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    alert('Configuration exported successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while exporting configuration');
            });
        }

        function importConfig() {
            // Create file input
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const configData = JSON.parse(e.target.result);
                            
                            // Validate config structure
                            if (!configData.config || !configData.config.items) {
                                alert('Invalid configuration file. Missing config.items');
                                return;
                            }
                            
                            // Show confirmation dialog
                            if (confirm('Are you sure you want to import this configuration? This will overwrite the current configuration.')) {
                                performImport(configData.config);
                            }
                        } catch (error) {
                            alert('Invalid JSON file: ' + error.message);
                        }
                    };
                    reader.readAsText(file);
                }
            };
            input.click();
        }

        function performImport(config) {
            fetch('{{ route("admin.sidebar-builder.import", $role) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    config: config,
                    overwrite: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuration imported successfully!');
                    location.reload(); // Reload to show imported config
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while importing configuration');
            });
        }

        function showPresets() {
            // Fetch available presets
            fetch('{{ route("admin.sidebar-builder.presets") }}', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    createPresetModal(data.data);
                } else {
                    alert('Error loading presets: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading presets');
            });
        }

        function createPresetModal(presets) {
            const modal = document.createElement('div');
            modal.id = 'presetModal';
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            
            const presetOptions = Object.entries(presets).map(([key, label]) => 
                `<option value="${key}">${label}</option>`
            ).join('');
            
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Apply Preset</h3>
                        <form id="presetForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Preset:</label>
                                <select name="preset_name" id="preset_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="">Choose a preset...</option>
                                    ${presetOptions}
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Apply to Role:</label>
                                <input type="text" name="role" id="role" value="{{ $role }}" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                            </div>
                            <div class="mb-4">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                This will overwrite the current configuration for this role.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closePresetModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    Apply Preset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Handle form submission
            document.getElementById('presetForm').addEventListener('submit', function(e) {
                e.preventDefault();
                applyPreset();
            });
        }

        function closePresetModal() {
            const modal = document.getElementById('presetModal');
            if (modal) {
                modal.remove();
            }
        }

        function applyPreset() {
            const formData = new FormData(document.getElementById('presetForm'));
            const data = {
                preset_name: formData.get('preset_name'),
                tenant_id: null
            };

            fetch('{{ route("admin.sidebar-builder.apply-preset", $role) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Preset applied successfully!');
                    closePresetModal();
                    location.reload(); // Reload to show updated config
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while applying preset');
            });
        }
    </script>
</body>
</html>
