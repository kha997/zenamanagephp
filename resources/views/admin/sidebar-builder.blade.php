@extends('layouts.simple')

@section('title', 'Sidebar Builder - Admin')
@section('page-title', 'Sidebar Builder')

@section('content')
<div class="sidebar-builder-container">
    <div class="builder-header">
        <div class="builder-controls">
            <select id="role-selector" class="form-select">
                <option value="super_admin">Super Admin</option>
                <option value="admin">Admin</option>
                <option value="project_manager">Project Manager</option>
                <option value="designer">Designer</option>
                <option value="site_engineer">Site Engineer</option>
                <option value="qc_engineer">QC Engineer</option>
                <option value="procurement">Procurement</option>
                <option value="finance">Finance</option>
                <option value="client">Client</option>
            </select>
            
            <button id="preview-btn" class="btn btn-secondary">
                <i class="fas fa-eye"></i> Preview
            </button>
            
            <button id="reset-btn" class="btn btn-warning">
                <i class="fas fa-undo"></i> Reset to Default
            </button>
            
            <button id="save-btn" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Configuration
            </button>
        </div>
    </div>

    <div class="builder-content">
        <div class="builder-left">
            <div class="builder-panel">
                <h3>Available Items</h3>
                <div id="available-items" class="items-list">
                    <!-- Available sidebar items will be loaded here -->
                </div>
            </div>
        </div>
        
        <div class="builder-center">
            <div class="builder-panel">
                <h3>Sidebar Configuration</h3>
                <div id="sidebar-config" class="config-list">
                    <!-- Current sidebar configuration will be shown here -->
                </div>
            </div>
        </div>
        
        <div class="builder-right">
            <div class="builder-panel">
                <h3>Preview</h3>
                <div id="sidebar-preview" class="preview-container">
                    <!-- Sidebar preview will be shown here -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sidebar-builder-container {
    padding: 20px;
    max-width: 1600px;
    margin: 0 auto;
}

.builder-header {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.builder-controls {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.form-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    min-width: 150px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    font-size: 0.875rem;
    font-weight: 500;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
}

.builder-content {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
}

.builder-panel {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
}

.builder-panel h3 {
    margin: 0 0 20px 0;
    color: #1f2937;
    font-size: 1.125rem;
    font-weight: 600;
}

.items-list,
.config-list {
    min-height: 400px;
}

.item-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    cursor: move;
    transition: all 0.2s;
}

.item-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.item-card.dragging {
    opacity: 0.5;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.item-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.875rem;
}

.item-icon {
    color: #6b7280;
    font-size: 0.875rem;
}

.item-details {
    font-size: 0.75rem;
    color: #64748b;
}

.config-item {
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    position: relative;
}

.config-item .item-header {
    margin-bottom: 8px;
}

.config-item .item-title {
    color: #0c4a6e;
}

.config-controls {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    gap: 5px;
}

.config-controls button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 3px;
    color: #6b7280;
    font-size: 0.75rem;
}

.config-controls button:hover {
    background: rgba(0,0,0,0.1);
}

.preview-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    padding: 20px;
    color: white;
    min-height: 400px;
}

.preview-sidebar {
    width: 100%;
}

.preview-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 5px;
    border-radius: 4px;
    transition: all 0.2s;
}

.preview-item:hover {
    background: rgba(255,255,255,0.1);
}

.preview-item i {
    width: 16px;
    margin-right: 8px;
    font-size: 0.875rem;
}

.preview-item span {
    font-size: 0.875rem;
}

.drop-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
    color: #64748b;
    font-size: 0.875rem;
    margin-bottom: 10px;
}

.drop-zone.drag-over {
    border-color: #2563eb;
    background: #eff6ff;
    color: #2563eb;
}

@media (max-width: 1200px) {
    .builder-content {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .builder-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-select {
        min-width: auto;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebarBuilder();
});

let currentRole = 'super_admin';
let sidebarConfig = [];
let availableItems = [];

function initializeSidebarBuilder() {
    loadAvailableItems();
    loadSidebarConfig();
    setupEventListeners();
}

function setupEventListeners() {
    document.getElementById('role-selector').addEventListener('change', function() {
        currentRole = this.value;
        loadSidebarConfig();
    });
    
    document.getElementById('preview-btn').addEventListener('click', function() {
        previewSidebar();
    });
    
    document.getElementById('reset-btn').addEventListener('click', function() {
        resetToDefault();
    });
    
    document.getElementById('save-btn').addEventListener('click', function() {
        saveConfiguration();
    });
}

function loadAvailableItems() {
    availableItems = [
        {
            id: 'dashboard',
            type: 'link',
            label: 'Dashboard',
            icon: 'fas fa-tachometer-alt',
            to: '/dashboard',
            enabled: true
        },
        {
            id: 'projects',
            type: 'link',
            label: 'Projects',
            icon: 'fas fa-building',
            to: '/projects',
            enabled: true
        },
        {
            id: 'tasks',
            type: 'link',
            label: 'Tasks',
            icon: 'fas fa-tasks',
            to: '/tasks',
            enabled: true
        },
        {
            id: 'team',
            type: 'link',
            label: 'Team',
            icon: 'fas fa-users',
            to: '/team',
            enabled: true
        },
        {
            id: 'reports',
            type: 'link',
            label: 'Reports',
            icon: 'fas fa-chart-bar',
            to: '/reports',
            enabled: true
        },
        {
            id: 'admin',
            type: 'link',
            label: 'Admin',
            icon: 'fas fa-cog',
            to: '/admin',
            enabled: true,
            required_permissions: ['admin.access']
        },
        {
            id: 'divider1',
            type: 'divider',
            label: 'Divider',
            enabled: true
        },
        {
            id: 'external_docs',
            type: 'external',
            label: 'Documentation',
            icon: 'fas fa-book',
            href: 'https://docs.example.com',
            enabled: true
        }
    ];
    
    renderAvailableItems();
}

function renderAvailableItems() {
    const container = document.getElementById('available-items');
    container.innerHTML = '';
    
    availableItems.forEach(item => {
        const itemCard = document.createElement('div');
        itemCard.className = 'item-card';
        itemCard.draggable = true;
        itemCard.dataset.itemId = item.id;
        
        itemCard.innerHTML = `
            <div class="item-header">
                <span class="item-title">${item.label}</span>
                <span class="item-icon">${item.icon || 'fas fa-circle'}</span>
            </div>
            <div class="item-details">
                Type: ${item.type} | ${item.to || item.href || 'N/A'}
            </div>
        `;
        
        itemCard.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', item.id);
            itemCard.classList.add('dragging');
        });
        
        itemCard.addEventListener('dragend', function() {
            itemCard.classList.remove('dragging');
        });
        
        container.appendChild(itemCard);
    });
}

function loadSidebarConfig() {
    fetch(`/api/sidebar/default/${currentRole}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                sidebarConfig = data.config.items || [];
                renderSidebarConfig();
                previewSidebar();
            } else {
                console.error('Failed to load sidebar config:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading sidebar config:', error);
        });
}

function renderSidebarConfig() {
    const container = document.getElementById('sidebar-config');
    container.innerHTML = '<div class="drop-zone">Drop items here to add to sidebar</div>';
    
    sidebarConfig.forEach((item, index) => {
        const configItem = document.createElement('div');
        configItem.className = 'config-item';
        configItem.dataset.index = index;
        
        configItem.innerHTML = `
            <div class="config-controls">
                <button onclick="toggleItem(${index})" title="Toggle">
                    <i class="fas fa-${item.enabled ? 'eye' : 'eye-slash'}"></i>
                </button>
                <button onclick="removeItem(${index})" title="Remove">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="item-header">
                <span class="item-title">${item.label}</span>
                <span class="item-icon">${item.icon || 'fas fa-circle'}</span>
            </div>
            <div class="item-details">
                Type: ${item.type} | Order: ${index + 1} | ${item.enabled ? 'Enabled' : 'Disabled'}
            </div>
        `;
        
        container.appendChild(configItem);
    });
    
    setupDropZone();
}

function setupDropZone() {
    const dropZone = document.querySelector('.drop-zone');
    
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        
        const itemId = e.dataTransfer.getData('text/plain');
        const item = availableItems.find(i => i.id === itemId);
        
        if (item) {
            addItemToConfig(item);
        }
    });
}

function addItemToConfig(item) {
    sidebarConfig.push({
        ...item,
        order: sidebarConfig.length
    });
    
    renderSidebarConfig();
    previewSidebar();
}

function toggleItem(index) {
    sidebarConfig[index].enabled = !sidebarConfig[index].enabled;
    renderSidebarConfig();
    previewSidebar();
}

function removeItem(index) {
    sidebarConfig.splice(index, 1);
    renderSidebarConfig();
    previewSidebar();
}

function previewSidebar() {
    const container = document.getElementById('sidebar-preview');
    container.innerHTML = '<div class="preview-sidebar">';
    
    sidebarConfig.forEach(item => {
        if (!item.enabled) return;
        
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item';
        
        if (item.type === 'divider') {
            previewItem.innerHTML = '<hr style="border-color: rgba(255,255,255,0.2); margin: 10px 0;">';
        } else {
            previewItem.innerHTML = `
                <i class="${item.icon || 'fas fa-circle'}"></i>
                <span>${item.label}</span>
            `;
        }
        
        container.querySelector('.preview-sidebar').appendChild(previewItem);
    });
    
    container.innerHTML += '</div>';
}

function resetToDefault() {
    if (confirm('Are you sure you want to reset to default configuration?')) {
        loadSidebarConfig();
    }
}

function saveConfiguration() {
    const config = {
        role_name: currentRole,
        config: {
            items: sidebarConfig
        },
        is_enabled: true
    };
    
    fetch('/api/admin/sidebar-configs', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(config)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Configuration saved successfully!');
        } else {
            alert('Failed to save configuration: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error saving configuration:', error);
        alert('Error saving configuration');
    });
}
</script>
@endsection