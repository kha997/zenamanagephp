@extends('layouts.app-layout')

@section('title', 'Custom Dashboard Builder - ZenaManage')

@section('content')
<div x-data="dashboardBuilder()" x-init="init()" class="min-h-screen bg-gray-50">
    
    <!-- Builder Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold text-gray-900">Dashboard Builder</h1>
                        <button @click="goToTemplates()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-layer-group mr-2"></i>Templates
                        </button>
                        <button @click="goToDataSources()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-database mr-2"></i>Data Sources
                        </button>
                        <button @click="goToCollaboration()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-users mr-2"></i>Collaboration
                        </button>
                        <button @click="goToMobile()" 
                                class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                            <i class="fas fa-mobile-alt mr-2"></i>Mobile
                        </button>
                        <button @click="goToFuture()" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-rocket mr-2"></i>Future
                        </button>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-full">
                            <i class="fas fa-edit mr-1"></i>Edit Mode
                        </span>
                        <span x-show="hasUnsavedChanges" class="px-3 py-1 text-sm bg-yellow-100 text-yellow-700 rounded-full">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Unsaved Changes
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Preview Button -->
                    <button @click="togglePreview()" 
                            :class="previewMode ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-eye mr-2"></i>
                        <span x-text="previewMode ? 'Exit Preview' : 'Preview'"></span>
                    </button>
                    
                    <!-- Save Button -->
                    <button @click="saveDashboard()" 
                            :disabled="!hasUnsavedChanges"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                        <i class="fas fa-save mr-2"></i>Save Dashboard
                    </button>
                    
                    <!-- Settings -->
                    <button @click="openSettings()" class="p-2 text-gray-700 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Builder Interface -->
    <div class="flex h-screen">
        
        <!-- Left Sidebar - Widget Library -->
        <div class="w-80 bg-white shadow-lg border-r border-gray-200 overflow-y-auto">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Widget Library</h2>
                
                <!-- Search Widgets -->
                <div class="mb-4">
                    <div class="relative">
                        <input type="text" 
                               x-model="widgetSearch" 
                               placeholder="Search widgets..."
                               class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                
                <!-- Widget Categories -->
                <div class="space-y-4">
                    <template x-for="category in filteredWidgetCategories" :key="category.name">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-2" x-text="category.name"></h3>
                            <div class="space-y-2">
                                <template x-for="widget in category.widgets" :key="widget.id">
                                    <div class="widget-item p-3 border border-gray-200 rounded-lg cursor-move hover:border-blue-500 hover:shadow-md transition-all"
                                         :draggable="true"
                                         @dragstart="startDrag($event, widget)"
                                         @dragend="endDrag($event)">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 rounded-lg" :class="widget.iconBg">
                                                <i :class="widget.icon" :class="widget.iconColor" class="text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium text-gray-900" x-text="widget.name"></h4>
                                                <p class="text-xs text-gray-500" x-text="widget.description"></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        
        <!-- Center - Canvas Area -->
        <div class="flex-1 flex flex-col">
            
            <!-- Canvas Toolbar -->
            <div class="bg-white border-b border-gray-200 px-6 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <!-- Grid Toggle -->
                        <button @click="toggleGrid()" 
                                :class="showGrid ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                                class="px-3 py-1 rounded-lg transition-colors">
                            <i class="fas fa-th mr-1"></i>Grid
                        </button>
                        
                        <!-- Zoom Controls -->
                        <div class="flex items-center space-x-2">
                            <button @click="zoomOut()" class="p-1 text-gray-600 hover:text-gray-900">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="text-sm text-gray-600" x-text="Math.round(zoomLevel * 100) + '%'"></span>
                            <button @click="zoomIn()" class="p-1 text-gray-600 hover:text-gray-900">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <!-- Layout Presets -->
                        <div class="flex items-center space-x-2">
                            <label class="text-sm text-gray-600">Layout:</label>
                            <select @change="applyLayoutPreset()" x-model="selectedLayout" 
                                    class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                                <option value="custom">Custom</option>
                                <option value="single">Single Column</option>
                                <option value="two-column">Two Column</option>
                                <option value="three-column">Three Column</option>
                                <option value="grid">Grid Layout</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <!-- Undo/Redo -->
                        <button @click="undo()" :disabled="!canUndo" 
                                class="p-2 text-gray-600 hover:text-gray-900 disabled:opacity-50">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button @click="redo()" :disabled="!canRedo" 
                                class="p-2 text-gray-600 hover:text-gray-900 disabled:opacity-50">
                            <i class="fas fa-redo"></i>
                        </button>
                        
                        <!-- Clear Canvas -->
                        <button @click="clearCanvas()" 
                                class="px-3 py-1 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                            <i class="fas fa-trash mr-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Canvas Area -->
            <div class="flex-1 overflow-auto bg-gray-100 p-6">
                <div class="canvas-container relative min-h-full" 
                     :style="'transform: scale(' + zoomLevel + '); transform-origin: top left;'">
                    
                    <!-- Grid Overlay -->
                    <div x-show="showGrid" class="absolute inset-0 pointer-events-none">
                        <div class="grid-overlay w-full h-full" 
                             :style="'background-image: linear-gradient(rgba(0,0,0,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.1) 1px, transparent 1px); background-size: 20px 20px;'"></div>
                    </div>
                    
                    <!-- Drop Zone -->
                    <div class="drop-zone min-h-96 border-2 border-dashed border-gray-300 rounded-lg p-6"
                         :class="isDragOver ? 'border-blue-500 bg-blue-50' : ''"
                         @dragover.prevent="handleDragOver($event)"
                         @dragleave="handleDragLeave($event)"
                         @drop="handleDrop($event)">
                        
                        <!-- Empty State -->
                        <div x-show="widgets.length === 0" class="text-center py-12">
                            <i class="fas fa-plus-circle text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Start Building Your Dashboard</h3>
                            <p class="text-gray-500 mb-4">Drag widgets from the library to get started</p>
                            <button @click="addSampleWidgets()" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-magic mr-2"></i>Add Sample Widgets
                            </button>
                        </div>
                        
                        <!-- Widgets Grid -->
                        <div class="widgets-grid grid gap-4" :class="gridColumns">
                            <template x-for="(widget, index) in widgets" :key="widget.id">
                                <div class="widget-container relative group"
                                     :class="widget.size"
                                     :data-widget-id="widget.id"
                                     @click="selectWidget(widget)"
                                     :class="selectedWidget?.id === widget.id ? 'ring-2 ring-blue-500' : ''">
                                    
                                    <!-- Widget Header -->
                                    <div class="widget-header flex items-center justify-between p-3 bg-white border-b border-gray-200">
                                        <div class="flex items-center space-x-2">
                                            <div class="p-1 rounded" :class="widget.iconBg">
                                                <i :class="widget.icon" :class="widget.iconColor" class="text-xs"></i>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900" x-text="widget.name"></span>
                                        </div>
                                        
                                        <!-- Widget Controls -->
                                        <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click.stop="duplicateWidget(widget)" 
                                                    class="p-1 text-gray-600 hover:text-gray-900">
                                                <i class="fas fa-copy text-xs"></i>
                                            </button>
                                            <button @click.stop="resizeWidget(widget)" 
                                                    class="p-1 text-gray-600 hover:text-gray-900">
                                                <i class="fas fa-expand text-xs"></i>
                                            </button>
                                            <button @click.stop="removeWidget(widget)" 
                                                    class="p-1 text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Widget Content -->
                                    <div class="widget-content p-4 bg-white">
                                        <div x-html="getWidgetContent(widget)"></div>
                                    </div>
                                    
                                    <!-- Resize Handles -->
                                    <div class="resize-handles absolute inset-0 pointer-events-none">
                                        <div class="resize-handle resize-handle-nw absolute top-0 left-0 w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100"></div>
                                        <div class="resize-handle resize-handle-ne absolute top-0 right-0 w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100"></div>
                                        <div class="resize-handle resize-handle-sw absolute bottom-0 left-0 w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100"></div>
                                        <div class="resize-handle resize-handle-se absolute bottom-0 right-0 w-2 h-2 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Sidebar - Widget Properties -->
        <div class="w-80 bg-white shadow-lg border-l border-gray-200 overflow-y-auto">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Widget Properties</h2>
                
                <div x-show="!selectedWidget" class="text-center py-8">
                    <i class="fas fa-mouse-pointer text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Select a widget to edit its properties</p>
                </div>
                
                <div x-show="selectedWidget" class="space-y-6">
                    <!-- Widget Info -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Widget Information</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                                <input type="text" 
                                       x-model="selectedWidget.name"
                                       @input="markAsChanged()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                                <textarea x-model="selectedWidget.description"
                                          @input="markAsChanged()"
                                          rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Widget Size -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Size</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="setWidgetSize('col-span-1')" 
                                    :class="selectedWidget.size === 'col-span-1' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                                    class="px-3 py-2 rounded-lg text-sm transition-colors">
                                Small
                            </button>
                            <button @click="setWidgetSize('col-span-2')" 
                                    :class="selectedWidget.size === 'col-span-2' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                                    class="px-3 py-2 rounded-lg text-sm transition-colors">
                                Medium
                            </button>
                            <button @click="setWidgetSize('col-span-3')" 
                                    :class="selectedWidget.size === 'col-span-3' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                                    class="px-3 py-2 rounded-lg text-sm transition-colors">
                                Large
                            </button>
                            <button @click="setWidgetSize('col-span-4')" 
                                    :class="selectedWidget.size === 'col-span-4' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                                    class="px-3 py-2 rounded-lg text-sm transition-colors">
                                Full
                            </button>
                        </div>
                    </div>
                    
                    <!-- Widget Settings -->
                    <div x-show="selectedWidget.type === 'chart'">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Chart Settings</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Chart Type</label>
                                <select x-model="selectedWidget.chartType"
                                        @change="initChart(selectedWidget); markAsChanged()"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                    <option value="line">Line Chart</option>
                                    <option value="bar">Bar Chart</option>
                                    <option value="pie">Pie Chart</option>
                                    <option value="donut">Donut Chart</option>
                                    <option value="area">Area Chart</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Data Source</label>
                                <select x-model="selectedWidget.dataSource"
                                        @change="updateChartData(selectedWidget); markAsChanged()"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                    <option value="revenue">Revenue</option>
                                    <option value="projects">Projects</option>
                                    <option value="tasks">Tasks</option>
                                    <option value="team">Team</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Chart Colors</label>
                                <div class="flex space-x-2">
                                    <input type="color" 
                                           :value="getChartColors(selectedWidget.dataSource)[0]"
                                           @change="updateChartColors(selectedWidget, $event.target.value, 0)"
                                           class="w-8 h-8 rounded border border-gray-300">
                                    <input type="color" 
                                           :value="getChartColors(selectedWidget.dataSource)[1]"
                                           @change="updateChartColors(selectedWidget, $event.target.value, 1)"
                                           class="w-8 h-8 rounded border border-gray-300">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Chart Height</label>
                                <input type="range" 
                                       min="150" 
                                       max="300" 
                                       step="10"
                                       x-model="selectedWidget.chartHeight"
                                       @change="updateChartHeight(selectedWidget); markAsChanged()"
                                       class="w-full">
                                <div class="text-xs text-gray-500 text-center" x-text="selectedWidget.chartHeight + 'px'"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- WebSocket Configuration -->
                    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">WebSocket Configuration</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- WebSocket URL -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">WebSocket URL</label>
                                <input type="text" 
                                       x-model="websocket.url"
                                       @change="markAsChanged()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Connection Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Connection Status</label>
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 rounded-full" 
                                         :class="websocket.isConnected ? 'bg-green-500' : 'bg-red-500'"></div>
                                    <span class="text-sm" 
                                          :class="websocket.isConnected ? 'text-green-600' : 'text-red-600'"
                                          x-text="websocket.isConnected ? 'Connected' : 'Disconnected'"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Authentication Status -->
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Authentication Status</h5>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="text-center">
                                    <div class="text-sm font-medium text-gray-600">Authentication</div>
                                    <div class="mt-1">
                                        <span x-show="websocket.auth.isAuthenticated" class="text-green-600">
                                            <i class="fas fa-check mr-1"></i>Authenticated
                                        </span>
                                        <span x-show="!websocket.auth.isAuthenticated" class="text-red-600">
                                            <i class="fas fa-times mr-1"></i>Not Authenticated
                                        </span>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-sm font-medium text-gray-600">User ID</div>
                                    <div class="text-xs text-gray-500 mt-1" x-text="websocket.auth.userId || 'N/A'"></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-sm font-medium text-gray-600">Tenant ID</div>
                                    <div class="text-xs text-gray-500 mt-1" x-text="websocket.auth.tenantId || 'N/A'"></div>
                                </div>
                            </div>
                            
                            <!-- Permissions -->
                            <div class="mt-4">
                                <div class="text-sm font-medium text-gray-600 mb-2">Permissions</div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="permission in websocket.auth.permissions" :key="permission">
                                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">
                                            <i class="fas fa-key mr-1"></i><span x-text="permission"></span>
                                        </span>
                                    </template>
                                    <span x-show="websocket.auth.permissions.length === 0" class="text-sm text-gray-500">
                                        No permissions loaded
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- WebSocket Actions -->
                        <div class="mt-6 flex items-center space-x-4">
                            <button @click="initWebSocket()" 
                                    :disabled="websocket.isConnected"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                                <i class="fas fa-plug mr-2"></i>Connect
                            </button>
                            
                            <button @click="websocket.connection?.close()" 
                                    :disabled="!websocket.isConnected"
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50">
                                <i class="fas fa-unlink mr-2"></i>Disconnect
                            </button>
                            
                            <button @click="subscribeToDataSources()" 
                                    :disabled="!websocket.isConnected || !websocket.auth.isAuthenticated"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                                <i class="fas fa-broadcast-tower mr-2"></i>Subscribe All
                            </button>
                            
                            <button @click="requestTokenRefresh()" 
                                    :disabled="!websocket.auth.isAuthenticated"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors disabled:opacity-50">
                                <i class="fas fa-sync-alt mr-2"></i>Refresh Token
                            </button>
                        </div>
                        
                        <!-- Subscriptions Status -->
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Active Subscriptions</h5>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="subscription in websocket.subscriptions" :key="subscription">
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">
                                        <i class="fas fa-check mr-1"></i><span x-text="subscription"></span>
                                    </span>
                                </template>
                                <span x-show="websocket.subscriptions.size === 0" class="text-sm text-gray-500">
                                    No active subscriptions
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- API Configuration -->
                    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">API Configuration</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Base URL -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Base URL</label>
                                <input type="text" 
                                       x-model="apiConfig.baseUrl"
                                       @change="markAsChanged()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Cache TTL -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cache TTL (minutes)</label>
                                <input type="number" 
                                       x-model="apiConfig.cacheTtl"
                                       @change="markAsChanged()"
                                       min="1" 
                                       max="60"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <!-- Endpoints Configuration -->
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">API Endpoints</h4>
                            <div class="space-y-3">
                                <template x-for="(endpoint, key) in apiConfig.endpoints" :key="key">
                                    <div class="flex items-center space-x-3">
                                        <label class="w-20 text-sm font-medium text-gray-600" x-text="key"></label>
                                        <input type="text" 
                                               x-model="apiConfig.endpoints[key]"
                                               @change="markAsChanged()"
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </template>
                            </div>
                        </div>
                        
                        <!-- API Actions -->
                        <div class="mt-6 flex items-center space-x-4">
                            <button @click="refreshAllCharts()" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-sync-alt mr-2"></i>Refresh All Data
                            </button>
                            
                            <button @click="clearCache()" 
                                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                <i class="fas fa-trash mr-2"></i>Clear Cache
                            </button>
                            
                            <button @click="testAPI()" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-flask mr-2"></i>Test API
                            </button>
                            
                            <button @click="toggleRealTimeMode()" 
                                    :class="websocket.isConnected ? 'bg-orange-600 hover:bg-orange-700' : 'bg-purple-600 hover:bg-purple-700'"
                                    class="px-4 py-2 text-white rounded-lg transition-colors">
                                <i class="fas fa-broadcast-tower mr-2"></i>
                                <span x-text="websocket.isConnected ? 'Real-time ON' : 'Real-time OFF'"></span>
                            </button>
                        </div>
                        
                        <!-- API Status -->
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">API Status</h5>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <template x-for="(loading, dataSource) in loadingStates" :key="dataSource">
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-600" x-text="dataSource"></div>
                                        <div class="mt-1">
                                            <span x-show="loading" class="text-blue-600">
                                                <i class="fas fa-spinner fa-spin mr-1"></i>Loading
                                            </span>
                                            <span x-show="!loading && !errorStates[dataSource]" class="text-green-600">
                                                <i class="fas fa-check mr-1"></i>Ready
                                            </span>
                                            <span x-show="!loading && errorStates[dataSource]" class="text-red-600">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>Error
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Widget Data -->
                    <div x-show="selectedWidget.type === 'kpi'">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">KPI Settings</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Value</label>
                                <input type="number" 
                                       x-model="selectedWidget.value"
                                       @input="markAsChanged()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Change (%)</label>
                                <input type="number" 
                                       x-model="selectedWidget.change"
                                       @input="markAsChanged()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Icon</label>
                                <select x-model="selectedWidget.icon"
                                        @change="markAsChanged()"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                    <option value="fas fa-chart-line">Line Chart</option>
                                    <option value="fas fa-chart-bar">Bar Chart</option>
                                    <option value="fas fa-dollar-sign">Dollar</option>
                                    <option value="fas fa-users">Users</option>
                                    <option value="fas fa-project-diagram">Projects</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Settings Modal -->
    <div x-show="showSettings" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Dashboard Settings</h3>
                <button @click="closeSettings()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dashboard Name</label>
                    <input type="text" 
                           x-model="dashboardName"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea x-model="dashboardDescription"
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Grid Columns</label>
                    <select x-model="gridColumns" 
                            @change="updateGridColumns()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="grid-cols-1">1 Column</option>
                        <option value="grid-cols-2">2 Columns</option>
                        <option value="grid-cols-3">3 Columns</option>
                        <option value="grid-cols-4">4 Columns</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button @click="closeSettings()" 
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancel
                </button>
                <button @click="saveSettings()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Save Settings
                </button>
            </div>
        </div>
    </div>
    
</div>

<!-- Include required JavaScript libraries -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/lodash@latest/lodash.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function dashboardBuilder() {
    return {
        // State
        widgets: [],
        selectedWidget: null,
        previewMode: false,
        showGrid: true,
        showSettings: false,
        zoomLevel: 1,
        isDragOver: false,
        hasUnsavedChanges: false,
        widgetSearch: '',
        dashboardName: 'My Dashboard',
        dashboardDescription: '',
        gridColumns: 'grid-cols-3',
        selectedLayout: 'custom',
        
        // API Integration
        apiConfig: {
            baseUrl: '/api/v1',
            cacheTtl: 5,
            endpoints: {
                revenue: '/analytics/revenue',
                projects: '/projects/stats',
                tasks: '/tasks/metrics',
                team: '/team/analytics',
                kpis: '/dashboard/kpis'
            },
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        },
        dataCache: {},
        loadingStates: {},
        errorStates: {},
        refreshIntervals: {},
        
        // WebSocket Integration
        websocket: {
            connection: null,
            url: 'ws://localhost:8080/ws',
            reconnectAttempts: 0,
            maxReconnectAttempts: 5,
            reconnectInterval: 3000,
            isConnected: false,
            subscriptions: new Set(),
            messageQueue: [],
            heartbeatInterval: null,
            lastHeartbeat: null,
            
            // Authentication
            auth: {
                token: null,
                refreshToken: null,
                tokenExpiry: null,
                isAuthenticated: false,
                authMethod: 'jwt', // jwt, bearer, api_key
                permissions: [],
                userId: null,
                tenantId: null
            }
        },
        
        // History for undo/redo
        history: [],
        historyIndex: -1,
        
        // Widget library
        widgetCategories: [
            {
                name: 'Charts',
                widgets: [
                    {
                        id: 'chart-line',
                        name: 'Line Chart',
                        description: 'Display trend data over time',
                        type: 'chart',
                        chartType: 'line',
                        dataSource: 'revenue',
                        chartHeight: 192,
                        icon: 'fas fa-chart-line',
                        iconBg: 'bg-blue-100',
                        iconColor: 'text-blue-600',
                        size: 'col-span-2'
                    },
                    {
                        id: 'chart-bar',
                        name: 'Bar Chart',
                        description: 'Compare values across categories',
                        type: 'chart',
                        chartType: 'bar',
                        dataSource: 'projects',
                        chartHeight: 192,
                        icon: 'fas fa-chart-bar',
                        iconBg: 'bg-green-100',
                        iconColor: 'text-green-600',
                        size: 'col-span-2'
                    },
                    {
                        id: 'chart-pie',
                        name: 'Pie Chart',
                        description: 'Show data distribution',
                        type: 'chart',
                        chartType: 'pie',
                        dataSource: 'team',
                        chartHeight: 192,
                        icon: 'fas fa-chart-pie',
                        iconBg: 'bg-purple-100',
                        iconColor: 'text-purple-600',
                        size: 'col-span-1'
                    },
                    {
                        id: 'chart-donut',
                        name: 'Donut Chart',
                        description: 'Advanced pie chart with center',
                        type: 'chart',
                        chartType: 'donut',
                        dataSource: 'revenue',
                        chartHeight: 192,
                        icon: 'fas fa-chart-pie',
                        iconBg: 'bg-orange-100',
                        iconColor: 'text-orange-600',
                        size: 'col-span-1'
                    },
                    {
                        id: 'chart-area',
                        name: 'Area Chart',
                        description: 'Filled area under line',
                        type: 'chart',
                        chartType: 'area',
                        dataSource: 'tasks',
                        chartHeight: 192,
                        icon: 'fas fa-chart-area',
                        iconBg: 'bg-indigo-100',
                        iconColor: 'text-indigo-600',
                        size: 'col-span-2'
                    }
                ]
            },
            {
                name: 'KPIs',
                widgets: [
                    {
                        id: 'kpi-revenue',
                        name: 'Revenue KPI',
                        description: 'Display revenue metrics',
                        type: 'kpi',
                        value: 125000,
                        change: 12.5,
                        icon: 'fas fa-dollar-sign',
                        iconBg: 'bg-green-100',
                        iconColor: 'text-green-600',
                        size: 'col-span-1'
                    },
                    {
                        id: 'kpi-projects',
                        name: 'Projects KPI',
                        description: 'Active projects count',
                        type: 'kpi',
                        value: 24,
                        change: 8.3,
                        icon: 'fas fa-project-diagram',
                        iconBg: 'bg-blue-100',
                        iconColor: 'text-blue-600',
                        size: 'col-span-1'
                    },
                    {
                        id: 'kpi-users',
                        name: 'Users KPI',
                        description: 'Active users count',
                        type: 'kpi',
                        value: 156,
                        change: -2.1,
                        icon: 'fas fa-users',
                        iconBg: 'bg-purple-100',
                        iconColor: 'text-purple-600',
                        size: 'col-span-1'
                    }
                ]
            },
            {
                name: 'Tables',
                widgets: [
                    {
                        id: 'table-recent',
                        name: 'Recent Activity',
                        description: 'Show recent user activity',
                        type: 'table',
                        icon: 'fas fa-table',
                        iconBg: 'bg-gray-100',
                        iconColor: 'text-gray-600',
                        size: 'col-span-2'
                    },
                    {
                        id: 'table-team',
                        name: 'Team Members',
                        description: 'Display team information',
                        type: 'table',
                        icon: 'fas fa-users',
                        iconBg: 'bg-blue-100',
                        iconColor: 'text-blue-600',
                        size: 'col-span-2'
                    }
                ]
            }
        ],
        
        // Computed properties
        get filteredWidgetCategories() {
            if (!this.widgetSearch) return this.widgetCategories;
            
            return this.widgetCategories.map(category => ({
                ...category,
                widgets: category.widgets.filter(widget => 
                    widget.name.toLowerCase().includes(this.widgetSearch.toLowerCase()) ||
                    widget.description.toLowerCase().includes(this.widgetSearch.toLowerCase())
                )
            })).filter(category => category.widgets.length > 0);
        },
        
        get canUndo() {
            return this.historyIndex > 0;
        },
        
        get canRedo() {
            return this.historyIndex < this.history.length - 1;
        },
        
        // Methods
        init() {
            console.log('🎨 Dashboard Builder initialized');
            this.loadTemplateIfExists();
            this.saveState();
            this.initWebSocket();
        },
        
        // Load Template if exists
        loadTemplateIfExists() {
            const templateData = localStorage.getItem('selected-template');
            if (templateData) {
                try {
                    const template = JSON.parse(templateData);
                    console.log('📋 Loading template:', template.name);
                    
                    // Update dashboard info
                    this.dashboardName = template.name;
                    this.dashboardDescription = template.description;
                    
                    // Load widgets
                    if (template.widgets && template.widgets.length > 0) {
                        this.widgets = template.widgets;
                        console.log(`📊 Loaded ${template.widgets.length} widgets from template`);
                        
                        // Initialize charts
                        this.$nextTick(() => {
                            this.widgets.filter(w => w.type === 'chart').forEach(this.initChart);
                        });
                    }
                    
                    // Clear template from localStorage
                    localStorage.removeItem('selected-template');
                    
                } catch (error) {
                    console.error('Error loading template:', error);
                }
            }
        },
        
        // Drag and Drop
        startDrag(event, widget) {
            event.dataTransfer.setData('application/json', JSON.stringify(widget));
            event.dataTransfer.effectAllowed = 'copy';
        },
        
        endDrag(event) {
            // Clean up
        },
        
        handleDragOver(event) {
            event.preventDefault();
            this.isDragOver = true;
        },
        
        handleDragLeave(event) {
            this.isDragOver = false;
        },
        
        handleDrop(event) {
            event.preventDefault();
            this.isDragOver = false;
            
            const widgetData = JSON.parse(event.dataTransfer.getData('application/json'));
            this.addWidget(widgetData);
        },
        
        addWidget(widgetData) {
            const newWidget = {
                ...widgetData,
                id: widgetData.id + '-' + Date.now(),
                position: this.widgets.length
            };
            
            this.widgets.push(newWidget);
            this.markAsChanged();
            this.saveState();
            
            // Initialize chart if it's a chart widget
            if (newWidget.type === 'chart') {
                this.$nextTick(() => {
                    this.initChart(newWidget);
                });
            }
        },
        
        selectWidget(widget) {
            this.selectedWidget = widget;
        },
        
        removeWidget(widget) {
            this.widgets = this.widgets.filter(w => w.id !== widget.id);
            if (this.selectedWidget?.id === widget.id) {
                this.selectedWidget = null;
            }
            this.markAsChanged();
            this.saveState();
        },
        
        duplicateWidget(widget) {
            const newWidget = {
                ...widget,
                id: widget.id + '-copy-' + Date.now(),
                name: widget.name + ' (Copy)'
            };
            
            this.widgets.push(newWidget);
            this.markAsChanged();
            this.saveState();
        },
        
        resizeWidget(widget) {
            // Cycle through sizes
            const sizes = ['col-span-1', 'col-span-2', 'col-span-3', 'col-span-4'];
            const currentIndex = sizes.indexOf(widget.size);
            const nextIndex = (currentIndex + 1) % sizes.length;
            widget.size = sizes[nextIndex];
            this.markAsChanged();
        },
        
        setWidgetSize(size) {
            if (this.selectedWidget) {
                this.selectedWidget.size = size;
                this.markAsChanged();
                
                // Reinitialize chart if it's a chart widget
                if (this.selectedWidget.type === 'chart') {
                    this.$nextTick(() => {
                        this.initChart(this.selectedWidget);
                    });
                }
            }
        },
        
        getWidgetContent(widget) {
            switch (widget.type) {
                case 'kpi':
                    return `
                        <div class="text-center">
                            <div class="text-3xl font-bold text-gray-900">${widget.value.toLocaleString()}</div>
                            <div class="text-sm ${widget.change >= 0 ? 'text-green-600' : 'text-red-600'}">
                                ${widget.change >= 0 ? '+' : ''}${widget.change}%
                            </div>
                        </div>
                    `;
                    
                case 'chart':
                    return `
                        <div class="h-48 relative">
                            <div id="chart-${widget.id}" class="w-full h-full"></div>
                        </div>
                    `;
                    
                case 'table':
                    return `
                        <div class="space-y-2">
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                <span class="text-sm font-medium">Item 1</span>
                                <span class="text-xs text-gray-500">Value 1</span>
                            </div>
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                <span class="text-sm font-medium">Item 2</span>
                                <span class="text-xs text-gray-500">Value 2</span>
                            </div>
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                <span class="text-sm font-medium">Item 3</span>
                                <span class="text-xs text-gray-500">Value 3</span>
                            </div>
                        </div>
                    `;
                    
                default:
                    return '<div class="text-center text-gray-500">Widget Content</div>';
            }
        },
        
        // Layout and Grid
        toggleGrid() {
            this.showGrid = !this.showGrid;
        },
        
        updateGridColumns() {
            this.markAsChanged();
        },
        
        applyLayoutPreset() {
            if (this.selectedLayout === 'single') {
                this.gridColumns = 'grid-cols-1';
            } else if (this.selectedLayout === 'two-column') {
                this.gridColumns = 'grid-cols-2';
            } else if (this.selectedLayout === 'three-column') {
                this.gridColumns = 'grid-cols-3';
            } else if (this.selectedLayout === 'grid') {
                this.gridColumns = 'grid-cols-4';
            }
            this.markAsChanged();
        },
        
        // Zoom
        zoomIn() {
            this.zoomLevel = Math.min(this.zoomLevel + 0.1, 2);
        },
        
        zoomOut() {
            this.zoomLevel = Math.max(this.zoomLevel - 0.1, 0.5);
        },
        
        // Preview
        togglePreview() {
            this.previewMode = !this.previewMode;
            if (this.previewMode) {
                this.selectedWidget = null;
            }
        },
        
        // Sample Data
        addSampleWidgets() {
            const sampleWidgets = [
                {
                    id: 'sample-kpi-1',
                    name: 'Revenue',
                    type: 'kpi',
                    value: 125000,
                    change: 12.5,
                    icon: 'fas fa-dollar-sign',
                    iconBg: 'bg-green-100',
                    iconColor: 'text-green-600',
                    size: 'col-span-1'
                },
                {
                    id: 'sample-chart-1',
                    name: 'Sales Trend',
                    type: 'chart',
                    chartType: 'line',
                    dataSource: 'revenue',
                    icon: 'fas fa-chart-line',
                    iconBg: 'bg-blue-100',
                    iconColor: 'text-blue-600',
                    size: 'col-span-2'
                },
                {
                    id: 'sample-table-1',
                    name: 'Recent Activity',
                    type: 'table',
                    icon: 'fas fa-table',
                    iconBg: 'bg-gray-100',
                    iconColor: 'text-gray-600',
                    size: 'col-span-2'
                }
            ];
            
            sampleWidgets.forEach(widget => {
                this.addWidget(widget);
            });
        },
        
        // Settings
        openSettings() {
            this.showSettings = true;
        },
        
        closeSettings() {
            this.showSettings = false;
        },
        
        saveSettings() {
            this.markAsChanged();
            this.closeSettings();
        },
        
        // State Management
        markAsChanged() {
            this.hasUnsavedChanges = true;
        },
        
        saveState() {
            const state = {
                widgets: [...this.widgets],
                gridColumns: this.gridColumns,
                dashboardName: this.dashboardName,
                dashboardDescription: this.dashboardDescription
            };
            
            this.history = this.history.slice(0, this.historyIndex + 1);
            this.history.push(JSON.parse(JSON.stringify(state)));
            this.historyIndex = this.history.length - 1;
        },
        
        undo() {
            if (this.canUndo) {
                this.historyIndex--;
                this.restoreState(this.history[this.historyIndex]);
            }
        },
        
        redo() {
            if (this.canRedo) {
                this.historyIndex++;
                this.restoreState(this.history[this.historyIndex]);
            }
        },
        
        restoreState(state) {
            this.widgets = [...state.widgets];
            this.gridColumns = state.gridColumns;
            this.dashboardName = state.dashboardName;
            this.dashboardDescription = state.dashboardDescription;
            this.selectedWidget = null;
        },
        
        clearCanvas() {
            if (confirm('Are you sure you want to clear all widgets?')) {
                this.widgets = [];
                this.selectedWidget = null;
                this.markAsChanged();
                this.saveState();
            }
        },
        
        // Chart Integration
        async initChart(widget) {
            const chartElement = document.getElementById(`chart-${widget.id}`);
            if (!chartElement || typeof ApexCharts === 'undefined') {
                console.warn('Chart element not found or ApexCharts not loaded');
                return;
            }
            
            // Destroy existing chart if it exists
            if (widget.chartInstance) {
                widget.chartInstance.destroy();
            }
            
            // Show loading state
            chartElement.innerHTML = `
                <div class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
                        <p class="text-sm text-gray-500">Loading chart data...</p>
                    </div>
                </div>
            `;
            
            try {
                const chartOptions = await this.getChartOptions(widget);
                widget.chartInstance = new ApexCharts(chartElement, chartOptions);
                widget.chartInstance.render();
            } catch (error) {
                console.error('Error initializing chart:', error);
                chartElement.innerHTML = `
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center text-red-500">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                            <p class="text-sm">Failed to load chart data</p>
                            <button onclick="this.closest('[x-data]').__x.$data.refreshChart('${widget.id}')" 
                                    class="mt-2 px-3 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200">
                                Retry
                            </button>
                        </div>
                    </div>
                `;
            }
        },
        
        async getChartOptions(widget) {
            const data = await this.getChartData(widget.dataSource);
            const baseOptions = {
                chart: {
                    type: widget.chartType,
                    height: widget.chartHeight || 192,
                    width: '100%',
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 800
                    }
                },
                colors: this.getChartColors(widget.dataSource),
                dataLabels: {
                    enabled: false
                },
                legend: {
                    show: false
                },
                stroke: {
                    width: 2
                },
                grid: {
                    show: false
                },
                xaxis: {
                    labels: {
                        show: false
                    },
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    }
                },
                yaxis: {
                    labels: {
                        show: false
                    }
                },
                tooltip: {
                    enabled: true,
                    theme: 'light'
                }
            };
            
            // Chart type specific options
            switch (widget.chartType) {
                case 'line':
                    return {
                        ...baseOptions,
                        series: [{
                            name: widget.dataSource,
                            data: data.values
                        }],
                        xaxis: {
                            ...baseOptions.xaxis,
                            categories: data.categories,
                            labels: {
                                show: true,
                                style: {
                                    fontSize: '10px'
                                }
                            }
                        },
                        stroke: {
                            width: 3,
                            curve: 'smooth'
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shade: 'light',
                                type: 'vertical',
                                shadeIntensity: 0.5,
                                gradientToColors: [this.getChartColors(widget.dataSource)[0] + '20'],
                                inverseColors: false,
                                opacityFrom: 0.8,
                                opacityTo: 0.1
                            }
                        }
                    };
                    
                case 'bar':
                    return {
                        ...baseOptions,
                        series: [{
                            name: widget.dataSource,
                            data: data.values
                        }],
                        xaxis: {
                            ...baseOptions.xaxis,
                            categories: data.categories,
                            labels: {
                                show: true,
                                style: {
                                    fontSize: '10px'
                                }
                            }
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                columnWidth: '60%'
                            }
                        }
                    };
                    
                case 'pie':
                case 'donut':
                    return {
                        ...baseOptions,
                        series: data.values,
                        labels: data.categories,
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: widget.chartType === 'donut' ? '70%' : '0%'
                                }
                            }
                        },
                        legend: {
                            show: true,
                            position: 'bottom',
                            fontSize: '10px'
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return val + "%"
                            }
                        }
                    };
                    
                case 'area':
                    return {
                        ...baseOptions,
                        series: [{
                            name: widget.dataSource,
                            data: data.values
                        }],
                        xaxis: {
                            ...baseOptions.xaxis,
                            categories: data.categories,
                            labels: {
                                show: true,
                                style: {
                                    fontSize: '10px'
                                }
                            }
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shade: 'light',
                                type: 'vertical',
                                shadeIntensity: 0.5,
                                gradientToColors: [this.getChartColors(widget.dataSource)[0] + '40'],
                                inverseColors: false,
                                opacityFrom: 0.8,
                                opacityTo: 0.3
                            }
                        },
                        stroke: {
                            width: 2,
                            curve: 'smooth'
                        }
                    };
                    
                default:
                    return baseOptions;
            }
        },
        
        // API Integration Methods
        async fetchDataFromAPI(dataSource, params = {}) {
            const cacheKey = `${dataSource}_${JSON.stringify(params)}`;
            
            // Check cache first
            if (this.dataCache[cacheKey] && this.isCacheValid(cacheKey)) {
                return this.dataCache[cacheKey];
            }
            
            // Set loading state
            this.loadingStates[dataSource] = true;
            this.errorStates[dataSource] = null;
            
            try {
                const endpoint = this.apiConfig.endpoints[dataSource];
                if (!endpoint) {
                    throw new Error(`No endpoint configured for data source: ${dataSource}`);
                }
                
                const url = new URL(this.apiConfig.baseUrl + endpoint, window.location.origin);
                
                // Add query parameters
                Object.keys(params).forEach(key => {
                    url.searchParams.append(key, params[key]);
                });
                
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        ...this.apiConfig.headers,
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error(`API request failed: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                
                // Cache the data
                this.dataCache[cacheKey] = {
                    data: data,
                    timestamp: Date.now(),
                    ttl: this.apiConfig.cacheTtl * 60000 // Convert minutes to milliseconds
                };
                
                this.loadingStates[dataSource] = false;
                return data;
                
            } catch (error) {
                console.error(`Error fetching data for ${dataSource}:`, error);
                this.errorStates[dataSource] = error.message;
                this.loadingStates[dataSource] = false;
                
                // Return fallback data
                return this.getFallbackData(dataSource);
            }
        },
        
        isCacheValid(cacheKey) {
            const cached = this.dataCache[cacheKey];
            if (!cached) return false;
            
            const now = Date.now();
            return (now - cached.timestamp) < cached.ttl;
        },
        
        getFallbackData(dataSource) {
            const fallbackData = {
                revenue: {
                    values: [10000, 12000, 15000, 18000, 22000, 25000, 28000, 30000, 32000, 35000],
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                    metadata: { source: 'fallback', timestamp: Date.now() }
                },
                projects: {
                    values: [5, 8, 12, 15, 18, 22, 25, 28, 30, 32],
                    categories: ['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10'],
                    metadata: { source: 'fallback', timestamp: Date.now() }
                },
                tasks: {
                    values: [45, 52, 38, 67, 89, 76, 54, 43, 65, 78],
                    categories: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8', 'Week 9', 'Week 10'],
                    metadata: { source: 'fallback', timestamp: Date.now() }
                },
                team: {
                    values: [25, 30, 35, 28, 32, 38, 42, 45, 48, 50],
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                    metadata: { source: 'fallback', timestamp: Date.now() }
                }
            };
            
            return fallbackData[dataSource] || fallbackData.revenue;
        },
        
        async getChartData(dataSource) {
            try {
                const data = await this.fetchDataFromAPI(dataSource);
                
                // Transform API response to chart format
                if (data.chart_data) {
                    return {
                        values: data.chart_data.values || data.values,
                        categories: data.chart_data.categories || data.categories,
                        metadata: data.metadata || {}
                    };
                }
                
                // Handle different API response formats
                if (Array.isArray(data)) {
                    return {
                        values: data.map(item => item.value || item.count || item.amount),
                        categories: data.map(item => item.label || item.name || item.date),
                        metadata: { source: 'api', timestamp: Date.now() }
                    };
                }
                
                // Default format
                return {
                    values: data.values || [],
                    categories: data.categories || [],
                    metadata: data.metadata || { source: 'api', timestamp: Date.now() }
                };
                
            } catch (error) {
                console.error('Error getting chart data:', error);
                return this.getFallbackData(dataSource);
            }
        },
        
        async getKPIData(dataSource) {
            try {
                const data = await this.fetchDataFromAPI('kpis', { type: dataSource });
                
                return {
                    value: data.value || data.count || data.total || 0,
                    change: data.change || data.growth || data.percentage || 0,
                    metadata: data.metadata || { source: 'api', timestamp: Date.now() }
                };
                
            } catch (error) {
                console.error('Error getting KPI data:', error);
                return {
                    value: 0,
                    change: 0,
                    metadata: { source: 'fallback', timestamp: Date.now() }
                };
            }
        },
        
        getChartColors(dataSource) {
            const colorSets = {
                revenue: ['#3b82f6', '#1d4ed8'],
                projects: ['#10b981', '#059669'],
                tasks: ['#f59e0b', '#d97706'],
                team: ['#8b5cf6', '#7c3aed']
            };
            
            return colorSets[dataSource] || colorSets.revenue;
        },
        
        async updateChartData(widget) {
            if (widget.type === 'chart' && widget.chartInstance) {
                try {
                    const newData = await this.getChartData(widget.dataSource);
                    
                    if (widget.chartType === 'pie' || widget.chartType === 'donut') {
                        widget.chartInstance.updateSeries(newData.values);
                        widget.chartInstance.updateOptions({
                            labels: newData.categories
                        });
                    } else {
                        widget.chartInstance.updateSeries([{
                            name: widget.dataSource,
                            data: newData.values
                        }]);
                        widget.chartInstance.updateOptions({
                            xaxis: {
                                categories: newData.categories
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error updating chart data:', error);
                }
            }
        },
        
        async refreshChart(widgetId) {
            const widget = this.widgets.find(w => w.id === widgetId);
            if (widget && widget.type === 'chart') {
                // Clear cache for this data source
                Object.keys(this.dataCache).forEach(key => {
                    if (key.startsWith(widget.dataSource)) {
                        delete this.dataCache[key];
                    }
                });
                
                await this.initChart(widget);
            }
        },
        
        async refreshAllCharts() {
            const chartWidgets = this.widgets.filter(w => w.type === 'chart');
            for (const widget of chartWidgets) {
                await this.refreshChart(widget.id);
            }
        },
        
        updateChartColors(widget, color, index) {
            if (widget.type === 'chart' && widget.chartInstance) {
                const colors = this.getChartColors(widget.dataSource);
                colors[index] = color;
                
                widget.chartInstance.updateOptions({
                    colors: colors
                });
            }
        },
        
        updateChartHeight(widget) {
            if (widget.type === 'chart' && widget.chartInstance) {
                widget.chartInstance.updateOptions({
                    chart: {
                        height: widget.chartHeight
                    }
                });
            }
        },
        
        // WebSocket Integration Methods
        async initWebSocket() {
            try {
                // Authenticate first
                await this.authenticateWebSocket();
                
                // Build WebSocket URL with authentication
                const wsUrl = this.buildAuthenticatedWebSocketUrl();
                
                this.websocket.connection = new WebSocket(wsUrl);
                
                this.websocket.connection.onopen = (event) => {
                    console.log('🔌 WebSocket connected with authentication');
                    this.websocket.isConnected = true;
                    this.websocket.reconnectAttempts = 0;
                    this.startHeartbeat();
                    this.processMessageQueue();
                    
                    // Send authentication message
                    this.sendAuthenticationMessage();
                    
                    // Subscribe to all active data sources
                    this.subscribeToDataSources();
                };
                
                this.websocket.connection.onmessage = (event) => {
                    this.handleWebSocketMessage(event);
                };
                
                this.websocket.connection.onclose = (event) => {
                    console.log('🔌 WebSocket disconnected');
                    this.websocket.isConnected = false;
                    this.stopHeartbeat();
                    
                    // Check if disconnected due to authentication failure
                    if (event.code === 1008 || event.code === 1002) {
                        console.error('🔐 Authentication failed, attempting to re-authenticate');
                        this.handleAuthenticationFailure();
                    } else if (!event.wasClean) {
                        this.attemptReconnect();
                    }
                };
                
                this.websocket.connection.onerror = (error) => {
                    console.error('🔌 WebSocket error:', error);
                    this.websocket.isConnected = false;
                };
                
            } catch (error) {
                console.error('Failed to initialize WebSocket:', error);
                this.websocket.isConnected = false;
            }
        },
        
        handleWebSocketMessage(event) {
            try {
                const message = JSON.parse(event.data);
                
                switch (message.type) {
                    case 'auth_success':
                        this.handleAuthenticationSuccess(message);
                        break;
                    case 'auth_failure':
                        this.handleAuthenticationFailure(message);
                        break;
                    case 'token_refresh':
                        this.handleTokenRefresh(message);
                        break;
                    case 'data_update':
                        this.handleDataUpdate(message);
                        break;
                    case 'heartbeat':
                        this.handleHeartbeat(message);
                        break;
                    case 'error':
                        this.handleWebSocketError(message);
                        break;
                    case 'subscription_confirmed':
                        this.handleSubscriptionConfirmed(message);
                        break;
                    case 'permission_denied':
                        this.handlePermissionDenied(message);
                        break;
                    default:
                        console.log('Unknown WebSocket message type:', message.type);
                }
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        },
        
        handleDataUpdate(message) {
            const { dataSource, data } = message;
            
            // Update cache with new data
            const cacheKey = `${dataSource}_realtime`;
            this.dataCache[cacheKey] = {
                data: data,
                timestamp: Date.now(),
                ttl: 300000 // 5 minutes TTL for real-time data
            };
            
            // Update charts that use this data source
            this.updateChartsWithDataSource(dataSource, data);
            
            console.log(`📊 Real-time data update for ${dataSource}:`, data);
        },
        
        handleHeartbeat(message) {
            this.websocket.lastHeartbeat = Date.now();
            console.log('💓 WebSocket heartbeat received');
        },
        
        // Authentication Methods
        async authenticateWebSocket() {
            try {
                // Get token from localStorage or API
                const token = await this.getValidToken();
                
                if (!token) {
                    throw new Error('No valid authentication token available');
                }
                
                // Parse JWT token to get expiry
                const tokenData = this.parseJWTToken(token);
                
                this.websocket.auth.token = token;
                this.websocket.auth.tokenExpiry = tokenData.exp * 1000; // Convert to milliseconds
                this.websocket.auth.isAuthenticated = true;
                this.websocket.auth.userId = tokenData.sub;
                this.websocket.auth.tenantId = tokenData.tenant_id;
                this.websocket.auth.permissions = tokenData.permissions || [];
                
                console.log('🔐 WebSocket authentication token loaded');
                
            } catch (error) {
                console.error('Failed to authenticate WebSocket:', error);
                this.websocket.auth.isAuthenticated = false;
                throw error;
            }
        },
        
        async getValidToken() {
            // Check if we have a token in localStorage
            let token = localStorage.getItem('auth_token');
            
            if (!token) {
                // Try to get token from API
                token = await this.fetchAuthToken();
            }
            
            if (token) {
                // Check if token is expired
                const tokenData = this.parseJWTToken(token);
                const now = Date.now();
                
                if (tokenData.exp * 1000 < now) {
                    // Token expired, try to refresh
                    token = await this.refreshAuthToken();
                }
            }
            
            return token;
        },
        
        async fetchAuthToken() {
            try {
                const response = await fetch('/api/v1/auth/token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'include'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    localStorage.setItem('auth_token', data.token);
                    return data.token;
                }
            } catch (error) {
                console.error('Failed to fetch auth token:', error);
            }
            
            return null;
        },
        
        async refreshAuthToken() {
            try {
                const refreshToken = localStorage.getItem('refresh_token');
                
                if (!refreshToken) {
                    throw new Error('No refresh token available');
                }
                
                const response = await fetch('/api/v1/auth/refresh', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${refreshToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'include'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    localStorage.setItem('auth_token', data.token);
                    localStorage.setItem('refresh_token', data.refresh_token);
                    return data.token;
                }
            } catch (error) {
                console.error('Failed to refresh auth token:', error);
            }
            
            return null;
        },
        
        parseJWTToken(token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                
                return JSON.parse(jsonPayload);
            } catch (error) {
                console.error('Failed to parse JWT token:', error);
                return null;
            }
        },
        
        buildAuthenticatedWebSocketUrl() {
            const baseUrl = this.websocket.url;
            const token = this.websocket.auth.token;
            
            if (!token) {
                throw new Error('No authentication token available');
            }
            
            // Add token as query parameter
            const url = new URL(baseUrl);
            url.searchParams.append('token', token);
            url.searchParams.append('auth_method', this.websocket.auth.authMethod);
            
            return url.toString();
        },
        
        sendAuthenticationMessage() {
            if (!this.websocket.isConnected) {
                return;
            }
            
            const authMessage = {
                type: 'authenticate',
                token: this.websocket.auth.token,
                authMethod: this.websocket.auth.authMethod,
                userId: this.websocket.auth.userId,
                tenantId: this.websocket.auth.tenantId,
                permissions: this.websocket.auth.permissions,
                timestamp: Date.now()
            };
            
            this.websocket.connection.send(JSON.stringify(authMessage));
            console.log('🔐 Authentication message sent');
        },
        
        handleAuthenticationSuccess(message) {
            console.log('✅ WebSocket authentication successful');
            this.websocket.auth.isAuthenticated = true;
            
            if (message.permissions) {
                this.websocket.auth.permissions = message.permissions;
            }
            
            // Clear any authentication errors
            delete this.errorStates['websocket_auth'];
        },
        
        handleAuthenticationFailure(message) {
            console.error('❌ WebSocket authentication failed:', message.error);
            this.websocket.auth.isAuthenticated = false;
            this.errorStates['websocket_auth'] = message.error || 'Authentication failed';
            
            // Clear tokens
            localStorage.removeItem('auth_token');
            localStorage.removeItem('refresh_token');
            
            // Close connection
            if (this.websocket.connection) {
                this.websocket.connection.close();
            }
        },
        
        handleTokenRefresh(message) {
            console.log('🔄 Token refreshed');
            this.websocket.auth.token = message.token;
            this.websocket.auth.tokenExpiry = message.expiry;
            
            // Update localStorage
            localStorage.setItem('auth_token', message.token);
        },
        
        handlePermissionDenied(message) {
            console.warn('🚫 Permission denied for:', message.resource);
            this.errorStates['websocket_permission'] = `Permission denied: ${message.resource}`;
        },
        
        handleWebSocketError(message) {
            console.error('WebSocket error:', message.error);
            this.errorStates['websocket'] = message.error;
        },
        
        handleSubscriptionConfirmed(message) {
            console.log(`✅ Subscribed to ${message.dataSource}`);
            this.websocket.subscriptions.add(message.dataSource);
        },
        
        subscribeToDataSources() {
            const dataSources = ['revenue', 'projects', 'tasks', 'team', 'kpis'];
            
            dataSources.forEach(dataSource => {
                this.subscribeToDataSource(dataSource);
            });
        },
        
        subscribeToDataSource(dataSource) {
            if (!this.websocket.isConnected || !this.websocket.auth.isAuthenticated) {
                this.queueMessage({
                    type: 'subscribe',
                    dataSource: dataSource
                });
                return;
            }
            
            // Check permissions for this data source
            if (!this.hasPermissionForDataSource(dataSource)) {
                console.warn(`🚫 No permission to subscribe to ${dataSource}`);
                this.errorStates[`permission_${dataSource}`] = `No permission to access ${dataSource}`;
                return;
            }
            
            const message = {
                type: 'subscribe',
                dataSource: dataSource,
                token: this.websocket.auth.token,
                userId: this.websocket.auth.userId,
                tenantId: this.websocket.auth.tenantId,
                timestamp: Date.now()
            };
            
            this.websocket.connection.send(JSON.stringify(message));
            console.log(`📡 Subscribing to ${dataSource} with authentication`);
        },
        
        hasPermissionForDataSource(dataSource) {
            const requiredPermissions = {
                revenue: ['analytics.read', 'revenue.read'],
                projects: ['projects.read', 'analytics.read'],
                tasks: ['tasks.read', 'analytics.read'],
                team: ['team.read', 'analytics.read'],
                kpis: ['dashboard.read', 'analytics.read']
            };
            
            const required = requiredPermissions[dataSource] || [];
            const userPermissions = this.websocket.auth.permissions || [];
            
            return required.some(permission => userPermissions.includes(permission));
        },
        
        unsubscribeFromDataSource(dataSource) {
            if (!this.websocket.isConnected) {
                return;
            }
            
            const message = {
                type: 'unsubscribe',
                dataSource: dataSource,
                timestamp: Date.now()
            };
            
            this.websocket.connection.send(JSON.stringify(message));
            this.websocket.subscriptions.delete(dataSource);
            console.log(`📡 Unsubscribed from ${dataSource}`);
        },
        
        queueMessage(message) {
            this.websocket.messageQueue.push(message);
        },
        
        processMessageQueue() {
            while (this.websocket.messageQueue.length > 0 && this.websocket.isConnected) {
                const message = this.websocket.messageQueue.shift();
                this.websocket.connection.send(JSON.stringify(message));
            }
        },
        
        startHeartbeat() {
            this.websocket.heartbeatInterval = setInterval(() => {
                if (this.websocket.isConnected && this.websocket.auth.isAuthenticated) {
                    // Check if token is about to expire
                    const now = Date.now();
                    const tokenExpiry = this.websocket.auth.tokenExpiry;
                    
                    if (tokenExpiry && (tokenExpiry - now) < 300000) { // 5 minutes before expiry
                        console.log('🔄 Token about to expire, requesting refresh');
                        this.requestTokenRefresh();
                    }
                    
                    this.websocket.connection.send(JSON.stringify({
                        type: 'ping',
                        token: this.websocket.auth.token,
                        timestamp: Date.now()
                    }));
                }
            }, 30000); // Send ping every 30 seconds
        },
        
        async requestTokenRefresh() {
            try {
                const newToken = await this.refreshAuthToken();
                if (newToken) {
                    this.websocket.auth.token = newToken;
                    const tokenData = this.parseJWTToken(newToken);
                    this.websocket.auth.tokenExpiry = tokenData.exp * 1000;
                    
                    console.log('🔄 Token refreshed successfully');
                }
            } catch (error) {
                console.error('Failed to refresh token:', error);
                this.handleAuthenticationFailure({ error: 'Token refresh failed' });
            }
        },
        
        stopHeartbeat() {
            if (this.websocket.heartbeatInterval) {
                clearInterval(this.websocket.heartbeatInterval);
                this.websocket.heartbeatInterval = null;
            }
        },
        
        attemptReconnect() {
            if (this.websocket.reconnectAttempts < this.websocket.maxReconnectAttempts) {
                this.websocket.reconnectAttempts++;
                console.log(`🔄 Attempting to reconnect (${this.websocket.reconnectAttempts}/${this.websocket.maxReconnectAttempts})`);
                
                setTimeout(() => {
                    this.initWebSocket();
                }, this.websocket.reconnectInterval);
            } else {
                console.error('❌ Max reconnection attempts reached');
                this.errorStates['websocket'] = 'Connection lost. Please refresh the page.';
            }
        },
        
        updateChartsWithDataSource(dataSource, data) {
            const chartWidgets = this.widgets.filter(w => 
                w.type === 'chart' && w.dataSource === dataSource
            );
            
            chartWidgets.forEach(widget => {
                if (widget.chartInstance) {
                    this.updateChartWithRealTimeData(widget, data);
                }
            });
        },
        
        updateChartWithRealTimeData(widget, data) {
            try {
                // Transform data to chart format
                const chartData = this.transformDataForChart(data, widget.chartType);
                
                if (widget.chartType === 'pie' || widget.chartType === 'donut') {
                    widget.chartInstance.updateSeries(chartData.values);
                    widget.chartInstance.updateOptions({
                        labels: chartData.categories
                    });
                } else {
                    widget.chartInstance.updateSeries([{
                        name: widget.dataSource,
                        data: chartData.values
                    }]);
                    widget.chartInstance.updateOptions({
                        xaxis: {
                            categories: chartData.categories
                        }
                    });
                }
                
                // Add real-time indicator
                this.showRealTimeIndicator(widget);
                
            } catch (error) {
                console.error('Error updating chart with real-time data:', error);
            }
        },
        
        transformDataForChart(data, chartType) {
            if (data.chart_data) {
                return {
                    values: data.chart_data.values || data.values,
                    categories: data.chart_data.categories || data.categories
                };
            }
            
            if (Array.isArray(data)) {
                return {
                    values: data.map(item => item.value || item.count || item.amount),
                    categories: data.map(item => item.label || item.name || item.date)
                };
            }
            
            return {
                values: data.values || [],
                categories: data.categories || []
            };
        },
        
        showRealTimeIndicator(widget) {
            const widgetElement = document.querySelector(`[data-widget-id="${widget.id}"]`);
            if (widgetElement) {
                // Add real-time indicator
                let indicator = widgetElement.querySelector('.realtime-indicator');
                if (!indicator) {
                    indicator = document.createElement('div');
                    indicator.className = 'realtime-indicator absolute top-2 right-2 w-2 h-2 bg-green-500 rounded-full animate-pulse';
                    widgetElement.appendChild(indicator);
                }
                
                // Remove indicator after 3 seconds
                setTimeout(() => {
                    if (indicator) {
                        indicator.remove();
                    }
                }, 3000);
            }
        },
        
        // API Management Methods
        clearCache() {
            this.dataCache = {};
            console.log('🗑️ Cache cleared');
        },
        
        async testAPI() {
            const testResults = {};
            
            for (const [dataSource, endpoint] of Object.entries(this.apiConfig.endpoints)) {
                try {
                    const startTime = Date.now();
                    await this.fetchDataFromAPI(dataSource);
                    const endTime = Date.now();
                    
                    testResults[dataSource] = {
                        status: 'success',
                        responseTime: endTime - startTime
                    };
                } catch (error) {
                    testResults[dataSource] = {
                        status: 'error',
                        message: error.message
                    };
                }
            }
            
            console.log('🧪 API Test Results:', testResults);
            
            // Show results in alert
            const resultsText = Object.entries(testResults)
                .map(([source, result]) => {
                    if (result.status === 'success') {
                        return `${source}: ✅ ${result.responseTime}ms`;
                    } else {
                        return `${source}: ❌ ${result.message}`;
                    }
                })
                .join('\n');
            
            alert(`API Test Results:\n\n${resultsText}`);
        },
        
        // Real-time Mode Toggle
        toggleRealTimeMode() {
            if (this.websocket.isConnected) {
                this.websocket.connection.close();
                console.log('🔌 Real-time mode disabled');
            } else {
                this.initWebSocket();
                console.log('🔌 Real-time mode enabled');
            }
        },
        
        // Save Dashboard
        saveDashboard() {
            const dashboardData = {
                name: this.dashboardName,
                description: this.dashboardDescription,
                widgets: this.widgets,
                gridColumns: this.gridColumns,
                apiConfig: this.apiConfig,
                websocketConfig: {
                    url: this.websocket.url,
                    maxReconnectAttempts: this.websocket.maxReconnectAttempts,
                    reconnectInterval: this.websocket.reconnectInterval
                },
                createdAt: new Date().toISOString(),
                updatedAt: new Date().toISOString()
            };
            
            // Save to localStorage for demo
            localStorage.setItem('custom-dashboard', JSON.stringify(dashboardData));
            
            this.hasUnsavedChanges = false;
            
            // Show success message
            alert('Dashboard saved successfully!');
            
            console.log('💾 Dashboard saved:', dashboardData);
        },
        
        // Go to Templates
        goToTemplates() {
            window.location.href = '/app/dashboard-templates';
        },
        
        // Go to Data Sources
        goToDataSources() {
            window.location.href = '/app/advanced-data-sources';
        },
        
        // Go to Collaboration
        goToCollaboration() {
            window.location.href = '/app/real-time-collaboration';
        },
        
        // Go to Mobile
        goToMobile() {
            window.location.href = '/app/mobile-dashboard-builder';
        },
        
        // Go to Future Enhancements
        goToFuture() {
            window.location.href = '/app/future-enhancements';
        }
    };
}
</script>

<style>
/* Dashboard Builder Styles */
.widget-item {
    transition: all 0.2s ease;
}

.widget-item:hover {
    transform: translateY(-2px);
}

.widget-container {
    transition: all 0.2s ease;
    min-height: 200px;
}

.widget-container:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.resize-handle {
    pointer-events: auto;
    cursor: nw-resize;
}

.resize-handle-nw { cursor: nw-resize; }
.resize-handle-ne { cursor: ne-resize; }
.resize-handle-sw { cursor: sw-resize; }
.resize-handle-se { cursor: se-resize; }

.grid-overlay {
    opacity: 0.3;
}

.drop-zone {
    transition: all 0.3s ease;
}

.canvas-container {
    transition: transform 0.2s ease;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .flex.h-screen {
        flex-direction: column;
    }
    
    .w-80 {
        width: 100%;
        height: auto;
    }
    
    .widgets-grid {
        grid-template-columns: 1fr !important;
    }
}

/* Focus states for accessibility */
button:focus,
input:focus,
select:focus,
textarea:focus {
    @apply outline-none ring-2 ring-blue-500 ring-opacity-50;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .bg-white {
        @apply border-2 border-black;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .transition-all,
    .transition-colors,
    .transition-opacity,
    .transition-transform {
        transition: none;
    }
}
</style>
@endsection
