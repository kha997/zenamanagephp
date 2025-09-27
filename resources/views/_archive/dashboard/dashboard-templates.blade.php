<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Templates Library - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Professional dashboard templates for different industries and use cases">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZenaManage">
    <meta name="msapplication-TileColor" content="#2563eb">
    <meta name="msapplication-config" content="/browserconfig.xml">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#2563eb">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/css/design-system.css" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash@latest/lodash.min.js"></script>
    
    <style>
        .template-card {
            transition: all 0.3s ease;
        }
        .template-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .template-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .category-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .industry-badge {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased">
    <div x-data="templateLibrary()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-layer-group text-blue-600 mr-2"></i>
                                Dashboard Templates
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Search & Filters -->
                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative">
                            <input type="text" 
                                   x-model="searchQuery"
                                   @input="filterTemplates()"
                                   placeholder="Search templates..."
                                   class="w-64 px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        
                        <!-- Category Filter -->
                        <select x-model="selectedCategory" 
                                @change="filterTemplates()"
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>
                            <option value="sales">Sales</option>
                            <option value="marketing">Marketing</option>
                            <option value="operations">Operations</option>
                            <option value="finance">Finance</option>
                            <option value="hr">Human Resources</option>
                            <option value="analytics">Analytics</option>
                        </select>
                        
                        <!-- Industry Filter -->
                        <select x-model="selectedIndustry" 
                                @change="filterTemplates()"
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Industries</option>
                            <option value="technology">Technology</option>
                            <option value="healthcare">Healthcare</option>
                            <option value="finance">Finance</option>
                            <option value="retail">Retail</option>
                            <option value="manufacturing">Manufacturing</option>
                            <option value="education">Education</option>
                        </select>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center space-x-3">
                        <button @click="showCreateTemplate = true" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Create Template
                        </button>
                        <button @click="goToBuilder()" 
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-cog mr-2"></i>Dashboard Builder
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-layer-group text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Templates</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="templates.length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-eye text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Views Today</p>
                            <p class="text-2xl font-bold text-gray-900">1,247</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-download text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Downloads</p>
                            <p class="text-2xl font-bold text-gray-900">892</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <i class="fas fa-star text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Avg Rating</p>
                            <p class="text-2xl font-bold text-gray-900">4.8</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Featured Templates -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Featured Templates</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <template x-for="template in featuredTemplates" :key="template.id">
                        <div class="template-card bg-white rounded-xl shadow-sm overflow-hidden">
                            <!-- Template Preview -->
                            <div class="template-preview h-48 relative">
                                <div class="absolute inset-0 bg-black bg-opacity-20"></div>
                                <div class="absolute top-4 left-4">
                                    <span class="category-badge px-3 py-1 text-white text-sm font-medium rounded-full" 
                                          x-text="template.category"></span>
                                </div>
                                <div class="absolute top-4 right-4">
                                    <span class="industry-badge px-3 py-1 text-white text-sm font-medium rounded-full" 
                                          x-text="template.industry"></span>
                                </div>
                                <div class="absolute bottom-4 left-4 text-white">
                                    <h3 class="text-lg font-semibold" x-text="template.name"></h3>
                                    <p class="text-sm opacity-90" x-text="template.description"></p>
                                </div>
                                <div class="absolute bottom-4 right-4 text-white">
                                    <div class="flex items-center">
                                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                                        <span class="text-sm" x-text="template.rating"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Template Info -->
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-2">
                                        <img :src="template.author.avatar" 
                                             :alt="template.author.name"
                                             class="w-8 h-8 rounded-full">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="template.author.name"></p>
                                            <p class="text-xs text-gray-500" x-text="template.createdAt"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-download mr-1"></i>
                                            <span x-text="template.downloads"></span>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-eye mr-1"></i>
                                            <span x-text="template.views"></span>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Template Stats -->
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500">Widgets</p>
                                        <p class="text-sm font-semibold text-gray-900" x-text="template.widgets"></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500">Charts</p>
                                        <p class="text-sm font-semibold text-gray-900" x-text="template.charts"></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500">KPIs</p>
                                        <p class="text-sm font-semibold text-gray-900" x-text="template.kpis"></p>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex space-x-2">
                                    <button @click="previewTemplate(template)" 
                                            class="flex-1 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                        <i class="fas fa-eye mr-2"></i>Preview
                                    </button>
                                    <button @click="useTemplate(template)" 
                                            class="flex-1 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-download mr-2"></i>Use Template
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- All Templates -->
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">All Templates</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" x-text="`${filteredTemplates.length} templates found`"></span>
                        <select x-model="sortBy" 
                                @change="sortTemplates()"
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="popular">Most Popular</option>
                            <option value="newest">Newest</option>
                            <option value="rating">Highest Rated</option>
                            <option value="downloads">Most Downloaded</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <template x-for="template in filteredTemplates" :key="template.id">
                        <div class="template-card bg-white rounded-xl shadow-sm overflow-hidden">
                            <!-- Template Preview -->
                            <div class="template-preview h-32 relative cursor-pointer" 
                                 @click="previewTemplate(template)">
                                <div class="absolute inset-0 bg-black bg-opacity-20"></div>
                                <div class="absolute top-2 left-2">
                                    <span class="category-badge px-2 py-1 text-white text-xs font-medium rounded-full" 
                                          x-text="template.category"></span>
                                </div>
                                <div class="absolute top-2 right-2">
                                    <span class="industry-badge px-2 py-1 text-white text-xs font-medium rounded-full" 
                                          x-text="template.industry"></span>
                                </div>
                                <div class="absolute bottom-2 left-2 text-white">
                                    <h3 class="text-sm font-semibold" x-text="template.name"></h3>
                                </div>
                                <div class="absolute bottom-2 right-2 text-white">
                                    <div class="flex items-center">
                                        <i class="fas fa-star text-yellow-400 text-xs mr-1"></i>
                                        <span class="text-xs" x-text="template.rating"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Template Info -->
                            <div class="p-4">
                                <p class="text-sm text-gray-600 mb-3" x-text="template.description"></p>
                                
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <img :src="template.author.avatar" 
                                             :alt="template.author.name"
                                             class="w-6 h-6 rounded-full">
                                        <span class="text-xs text-gray-500" x-text="template.author.name"></span>
                                    </div>
                                    <span class="text-xs text-gray-500" x-text="template.createdAt"></span>
                                </div>
                                
                                <!-- Template Stats -->
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500">Widgets</p>
                                        <p class="text-xs font-semibold text-gray-900" x-text="template.widgets"></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500">Charts</p>
                                        <p class="text-xs font-semibold text-gray-900" x-text="template.charts"></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500">KPIs</p>
                                        <p class="text-xs font-semibold text-gray-900" x-text="template.kpis"></p>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex space-x-2">
                                    <button @click="previewTemplate(template)" 
                                            class="flex-1 px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs hover:bg-gray-200 transition-colors">
                                        <i class="fas fa-eye mr-1"></i>Preview
                                    </button>
                                    <button @click="useTemplate(template)" 
                                            class="flex-1 px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-download mr-1"></i>Use
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>

        <!-- Template Preview Modal -->
        <div x-show="showPreview" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
             @click="showPreview = false">
            <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900" x-text="selectedTemplate?.name"></h3>
                            <p class="text-sm text-gray-600" x-text="selectedTemplate?.description"></p>
                        </div>
                        <button @click="showPreview = false" 
                                class="p-2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Template Preview -->
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Template Preview</h4>
                            <div class="bg-gray-100 rounded-lg p-4 h-64">
                                <div class="grid grid-cols-2 gap-2 h-full">
                                    <div class="bg-white rounded p-2">
                                        <div class="h-8 bg-blue-200 rounded mb-2"></div>
                                        <div class="h-4 bg-gray-200 rounded mb-1"></div>
                                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                    </div>
                                    <div class="bg-white rounded p-2">
                                        <div class="h-8 bg-green-200 rounded mb-2"></div>
                                        <div class="h-4 bg-gray-200 rounded mb-1"></div>
                                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                    <div class="bg-white rounded p-2">
                                        <div class="h-8 bg-purple-200 rounded mb-2"></div>
                                        <div class="h-4 bg-gray-200 rounded mb-1"></div>
                                        <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                                    </div>
                                    <div class="bg-white rounded p-2">
                                        <div class="h-8 bg-orange-200 rounded mb-2"></div>
                                        <div class="h-4 bg-gray-200 rounded mb-1"></div>
                                        <div class="h-4 bg-gray-200 rounded w-4/5"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Template Details -->
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Template Details</h4>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Category</span>
                                    <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.category"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Industry</span>
                                    <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.industry"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Widgets</span>
                                    <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.widgets"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Charts</span>
                                    <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.charts"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">KPIs</span>
                                    <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.kpis"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Rating</span>
                                    <div class="flex items-center">
                                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                                        <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.rating"></span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Downloads</span>
                                    <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.downloads"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Author</span>
                                    <div class="flex items-center space-x-2">
                                        <img :src="selectedTemplate?.author.avatar" 
                                             :alt="selectedTemplate?.author.name"
                                             class="w-6 h-6 rounded-full">
                                        <span class="text-sm font-medium text-gray-900" x-text="selectedTemplate?.author.name"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <button @click="likeTemplate(selectedTemplate)" 
                                    class="flex items-center space-x-2 px-4 py-2 text-gray-600 hover:text-red-600 transition-colors">
                                <i class="fas fa-heart" :class="selectedTemplate?.liked ? 'text-red-600' : 'text-gray-400'"></i>
                                <span class="text-sm" x-text="selectedTemplate?.likes || 0"></span>
                            </button>
                            <button @click="shareTemplate(selectedTemplate)" 
                                    class="flex items-center space-x-2 px-4 py-2 text-gray-600 hover:text-blue-600 transition-colors">
                                <i class="fas fa-share"></i>
                                <span class="text-sm">Share</span>
                            </button>
                        </div>
                        <div class="flex space-x-3">
                            <button @click="showPreview = false" 
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button @click="useTemplate(selectedTemplate)" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-download mr-2"></i>Use Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Template Modal -->
        <div x-show="showCreateTemplate" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
             @click="showCreateTemplate = false">
            <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900">Create New Template</h3>
                        <button @click="showCreateTemplate = false" 
                                class="p-2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <form @submit.prevent="createTemplate()">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Template Name</label>
                                <input type="text" 
                                       x-model="newTemplate.name"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea x-model="newTemplate.description"
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                    <select x-model="newTemplate.category"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select Category</option>
                                        <option value="sales">Sales</option>
                                        <option value="marketing">Marketing</option>
                                        <option value="operations">Operations</option>
                                        <option value="finance">Finance</option>
                                        <option value="hr">Human Resources</option>
                                        <option value="analytics">Analytics</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Industry</label>
                                    <select x-model="newTemplate.industry"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select Industry</option>
                                        <option value="technology">Technology</option>
                                        <option value="healthcare">Healthcare</option>
                                        <option value="finance">Finance</option>
                                        <option value="retail">Retail</option>
                                        <option value="manufacturing">Manufacturing</option>
                                        <option value="education">Education</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                                <input type="text" 
                                       x-model="newTemplate.tags"
                                       placeholder="Enter tags separated by commas"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200">
                    <div class="flex items-center justify-end space-x-3">
                        <button @click="showCreateTemplate = false" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="createTemplate()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Create Template
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function templateLibrary() {
            return {
                // State
                templates: [],
                filteredTemplates: [],
                featuredTemplates: [],
                searchQuery: '',
                selectedCategory: '',
                selectedIndustry: '',
                sortBy: 'popular',
                showPreview: false,
                showCreateTemplate: false,
                selectedTemplate: null,
                newTemplate: {
                    name: '',
                    description: '',
                    category: '',
                    industry: '',
                    tags: ''
                },

                // Initialize
                init() {
                    this.loadTemplates();
                    this.filterTemplates();
                },

                // Load Templates
                loadTemplates() {
                    this.templates = [
                        {
                            id: 1,
                            name: 'Sales Performance Dashboard',
                            description: 'Comprehensive sales metrics with revenue tracking, conversion rates, and pipeline analysis.',
                            category: 'sales',
                            industry: 'technology',
                            widgets: 12,
                            charts: 6,
                            kpis: 4,
                            rating: 4.8,
                            downloads: 1247,
                            views: 3421,
                            likes: 89,
                            liked: false,
                            createdAt: '2 days ago',
                            author: {
                                name: 'John Smith',
                                avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['sales', 'revenue', 'conversion', 'pipeline']
                        },
                        {
                            id: 2,
                            name: 'Marketing Analytics Hub',
                            description: 'Track campaign performance, customer acquisition costs, and marketing ROI.',
                            category: 'marketing',
                            industry: 'retail',
                            widgets: 15,
                            charts: 8,
                            kpis: 6,
                            rating: 4.9,
                            downloads: 892,
                            views: 2156,
                            likes: 67,
                            liked: false,
                            createdAt: '1 week ago',
                            author: {
                                name: 'Sarah Johnson',
                                avatar: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['marketing', 'campaigns', 'roi', 'acquisition']
                        },
                        {
                            id: 3,
                            name: 'Operations Management',
                            description: 'Monitor operational efficiency, resource utilization, and process optimization.',
                            category: 'operations',
                            industry: 'manufacturing',
                            widgets: 18,
                            charts: 10,
                            kpis: 8,
                            rating: 4.7,
                            downloads: 654,
                            views: 1890,
                            likes: 45,
                            liked: false,
                            createdAt: '3 days ago',
                            author: {
                                name: 'Mike Chen',
                                avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['operations', 'efficiency', 'resources', 'optimization']
                        },
                        {
                            id: 4,
                            name: 'Financial Dashboard',
                            description: 'Comprehensive financial metrics including P&L, cash flow, and budget tracking.',
                            category: 'finance',
                            industry: 'finance',
                            widgets: 14,
                            charts: 7,
                            kpis: 5,
                            rating: 4.9,
                            downloads: 1123,
                            views: 2789,
                            likes: 78,
                            liked: false,
                            createdAt: '5 days ago',
                            author: {
                                name: 'Emily Davis',
                                avatar: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['finance', 'profit', 'cashflow', 'budget']
                        },
                        {
                            id: 5,
                            name: 'HR Analytics Suite',
                            description: 'Track employee performance, retention rates, and HR metrics.',
                            category: 'hr',
                            industry: 'technology',
                            widgets: 16,
                            charts: 9,
                            kpis: 7,
                            rating: 4.6,
                            downloads: 567,
                            views: 1456,
                            likes: 34,
                            liked: false,
                            createdAt: '1 week ago',
                            author: {
                                name: 'David Wilson',
                                avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['hr', 'employees', 'retention', 'performance']
                        },
                        {
                            id: 6,
                            name: 'Healthcare Analytics',
                            description: 'Patient metrics, treatment outcomes, and healthcare operational data.',
                            category: 'analytics',
                            industry: 'healthcare',
                            widgets: 20,
                            charts: 12,
                            kpis: 8,
                            rating: 4.8,
                            downloads: 789,
                            views: 2034,
                            likes: 56,
                            liked: false,
                            createdAt: '4 days ago',
                            author: {
                                name: 'Dr. Lisa Brown',
                                avatar: 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['healthcare', 'patients', 'outcomes', 'treatment']
                        },
                        {
                            id: 7,
                            name: 'E-commerce Dashboard',
                            description: 'Online sales, customer behavior, and e-commerce performance metrics.',
                            category: 'sales',
                            industry: 'retail',
                            widgets: 13,
                            charts: 6,
                            kpis: 5,
                            rating: 4.7,
                            downloads: 945,
                            views: 2234,
                            likes: 42,
                            liked: false,
                            createdAt: '6 days ago',
                            author: {
                                name: 'Alex Rodriguez',
                                avatar: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['ecommerce', 'online', 'customers', 'behavior']
                        },
                        {
                            id: 8,
                            name: 'Education Analytics',
                            description: 'Student performance, enrollment metrics, and educational outcomes.',
                            category: 'analytics',
                            industry: 'education',
                            widgets: 17,
                            charts: 9,
                            kpis: 6,
                            rating: 4.5,
                            downloads: 423,
                            views: 1123,
                            likes: 28,
                            liked: false,
                            createdAt: '2 weeks ago',
                            author: {
                                name: 'Prof. Maria Garcia',
                                avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=32&h=32&fit=crop&crop=face'
                            },
                            tags: ['education', 'students', 'enrollment', 'outcomes']
                        }
                    ];

                    this.featuredTemplates = this.templates.slice(0, 3);
                },

                // Filter Templates
                filterTemplates() {
                    let filtered = this.templates;

                    // Search filter
                    if (this.searchQuery) {
                        const query = this.searchQuery.toLowerCase();
                        filtered = filtered.filter(template => 
                            template.name.toLowerCase().includes(query) ||
                            template.description.toLowerCase().includes(query) ||
                            template.tags.some(tag => tag.toLowerCase().includes(query))
                        );
                    }

                    // Category filter
                    if (this.selectedCategory) {
                        filtered = filtered.filter(template => template.category === this.selectedCategory);
                    }

                    // Industry filter
                    if (this.selectedIndustry) {
                        filtered = filtered.filter(template => template.industry === this.selectedIndustry);
                    }

                    this.filteredTemplates = filtered;
                    this.sortTemplates();
                },

                // Sort Templates
                sortTemplates() {
                    switch (this.sortBy) {
                        case 'popular':
                            this.filteredTemplates.sort((a, b) => b.views - a.views);
                            break;
                        case 'newest':
                            this.filteredTemplates.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
                            break;
                        case 'rating':
                            this.filteredTemplates.sort((a, b) => b.rating - a.rating);
                            break;
                        case 'downloads':
                            this.filteredTemplates.sort((a, b) => b.downloads - a.downloads);
                            break;
                    }
                },

                // Preview Template
                previewTemplate(template) {
                    this.selectedTemplate = template;
                    this.showPreview = true;
                },

                // Use Template
                useTemplate(template) {
                    // Save template to localStorage for dashboard builder
                    const templateData = {
                        name: template.name,
                        description: template.description,
                        widgets: this.generateTemplateWidgets(template),
                        gridColumns: 'grid-cols-3',
                        createdAt: new Date().toISOString()
                    };

                    localStorage.setItem('selected-template', JSON.stringify(templateData));
                    
                    // Redirect to dashboard builder
                    window.location.href = '/app/dashboard-builder';
                },

                // Generate Template Widgets
                generateTemplateWidgets(template) {
                    const widgets = [];
                    
                    // Generate KPI widgets
                    for (let i = 0; i < template.kpis; i++) {
                        widgets.push({
                            id: `kpi-${i}-${Date.now()}`,
                            type: 'kpi',
                            name: `KPI ${i + 1}`,
                            size: 'col-span-1',
                            value: Math.floor(Math.random() * 1000),
                            change: Math.floor(Math.random() * 20) - 10,
                            icon: 'fas fa-chart-line',
                            iconColor: 'text-blue-600',
                            iconBg: 'bg-blue-100'
                        });
                    }

                    // Generate Chart widgets
                    for (let i = 0; i < template.charts; i++) {
                        widgets.push({
                            id: `chart-${i}-${Date.now()}`,
                            type: 'chart',
                            name: `Chart ${i + 1}`,
                            size: 'col-span-1',
                            chartType: ['line', 'bar', 'pie', 'donut', 'area'][i % 5],
                            dataSource: ['revenue', 'projects', 'tasks', 'team'][i % 4],
                            chartHeight: 200,
                            icon: 'fas fa-chart-bar',
                            iconColor: 'text-green-600',
                            iconBg: 'bg-green-100'
                        });
                    }

                    // Generate Table widgets
                    const remainingWidgets = template.widgets - template.kpis - template.charts;
                    for (let i = 0; i < remainingWidgets; i++) {
                        widgets.push({
                            id: `table-${i}-${Date.now()}`,
                            type: 'table',
                            name: `Table ${i + 1}`,
                            size: 'col-span-1',
                            icon: 'fas fa-table',
                            iconColor: 'text-purple-600',
                            iconBg: 'bg-purple-100'
                        });
                    }

                    return widgets;
                },

                // Like Template
                likeTemplate(template) {
                    if (template.liked) {
                        template.likes--;
                        template.liked = false;
                    } else {
                        template.likes++;
                        template.liked = true;
                    }
                },

                // Share Template
                shareTemplate(template) {
                    if (navigator.share) {
                        navigator.share({
                            title: template.name,
                            text: template.description,
                            url: window.location.href
                        });
                    } else {
                        // Fallback to clipboard
                        navigator.clipboard.writeText(window.location.href);
                        alert('Template link copied to clipboard!');
                    }
                },

                // Create Template
                createTemplate() {
                    if (!this.newTemplate.name || !this.newTemplate.category || !this.newTemplate.industry) {
                        alert('Please fill in all required fields');
                        return;
                    }

                    const template = {
                        id: Date.now(),
                        name: this.newTemplate.name,
                        description: this.newTemplate.description,
                        category: this.newTemplate.category,
                        industry: this.newTemplate.industry,
                        widgets: 0,
                        charts: 0,
                        kpis: 0,
                        rating: 0,
                        downloads: 0,
                        views: 0,
                        likes: 0,
                        liked: false,
                        createdAt: 'Just now',
                        author: {
                            name: 'You',
                            avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face'
                        },
                        tags: this.newTemplate.tags.split(',').map(tag => tag.trim()).filter(tag => tag)
                    };

                    this.templates.unshift(template);
                    this.filterTemplates();
                    this.showCreateTemplate = false;
                    
                    // Reset form
                    this.newTemplate = {
                        name: '',
                        description: '',
                        category: '',
                        industry: '',
                        tags: ''
                    };

                    alert('Template created successfully!');
                },

                // Go to Builder
                goToBuilder() {
                    window.location.href = '/app/dashboard-builder';
                }
            };
        }
    </script>
</body>
</html>
