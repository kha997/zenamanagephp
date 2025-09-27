@extends('layouts.app-layout')

@section('title', 'Professional Dashboard - ZenaManage')

@section('content')
<div x-data="professionalDashboard()" x-init="init()" class="min-h-screen bg-gray-50">
    
    <!-- Professional Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <!-- Search Bar -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               placeholder="Type to Search" 
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <!-- Right Side Navigation -->
                <div class="flex items-center space-x-4">
                    <!-- Theme Dropdown -->
                    <div class="relative">
                        <button class="flex items-center space-x-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="fas fa-palette"></i>
                            <span>Theme</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                    </div>
                    
                    <!-- Notifications -->
                    <button class="relative p-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-bell"></i>
                        <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-400"></span>
                    </button>
                    
                    <!-- Settings -->
                    <button class="p-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-cog"></i>
                    </button>
                    
                    <!-- User Avatar -->
                    <div class="flex items-center space-x-2">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face" 
                             alt="User Avatar" 
                             class="w-8 h-8 rounded-full">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- KPI Cards Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Pageviews Card -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Pageviews</p>
                        <p class="text-2xl font-bold text-gray-900">17,356</p>
                        <div class="flex items-center mt-1">
                            <span class="text-sm text-green-600 font-medium">+25.8%</span>
                            <span class="text-xs text-gray-500 ml-2">EPC 308.20</span>
                        </div>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-eye text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Click Card -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Click</p>
                        <p class="text-2xl font-bold text-gray-900">2,784</p>
                        <div class="flex items-center mt-1">
                            <span class="text-sm text-green-600 font-medium">+25.8%</span>
                            <span class="text-xs text-gray-500 ml-2">Related Value: 77359</span>
                        </div>
                    </div>
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-mouse-pointer text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Commission Card -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Commission</p>
                        <p class="text-2xl font-bold text-gray-900">$1,658</p>
                        <div class="flex items-center mt-1">
                            <span class="text-sm text-green-600 font-medium">+25.6%</span>
                            <span class="text-xs text-gray-500 ml-2">Related Value: 77:359</span>
                        </div>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Sales Card -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Sales</p>
                        <p class="text-2xl font-bold text-gray-900">$8,759</p>
                        <div class="flex items-center mt-1">
                            <span class="text-sm text-green-600 font-medium">+25.6%</span>
                            <span class="text-xs text-gray-500 ml-2">Related Value: 13.65</span>
                        </div>
                    </div>
                    <div class="p-3 bg-orange-100 rounded-lg">
                        <i class="fas fa-dollar-sign text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column -->
            <div class="space-y-8">
                
                <!-- Meeting Schedules -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Meeting Schedules</h3>
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=40&h=40&fit=crop&crop=face" 
                                 alt="Avatar" class="w-10 h-10 rounded-full">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">Call with woods</h4>
                                <p class="text-xs text-gray-500">21 Jul 08:20-10:20</p>
                            </div>
                            <span class="px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded-full">Business</span>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=40&h=40&fit=crop&crop=face" 
                                 alt="Avatar" class="w-10 h-10 rounded-full">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">Conference call</h4>
                                <p class="text-xs text-gray-500">22 Jul 02:00-3:30</p>
                            </div>
                            <span class="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded-full">Dinner</span>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=40&h=40&fit=crop&crop=face" 
                                 alt="Avatar" class="w-10 h-10 rounded-full">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">Meeting with John</h4>
                                <p class="text-xs text-gray-500">22 Jul 11:15-12:15</p>
                            </div>
                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">Meetup</span>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=40&h=40&fit=crop&crop=face" 
                                 alt="Avatar" class="w-10 h-10 rounded-full">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">Meeting with Sara</h4>
                                <p class="text-xs text-gray-500">23 Jul 07:30-08:30</p>
                            </div>
                            <span class="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded-full">Dinner</span>
                        </div>
                    </div>
                </div>
                
                <!-- Sales by Countries -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Sales Overview</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img src="https://flagcdn.com/w20/us.png" alt="US Flag" class="w-5 h-4">
                                <span class="text-sm font-medium text-gray-900">United States of America</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">$8,564k</p>
                                <p class="text-xs text-red-600">-7.0%</p>
                                <p class="text-xs text-gray-500">452k</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img src="https://flagcdn.com/w20/ca.png" alt="Canada Flag" class="w-5 h-4">
                                <span class="text-sm font-medium text-gray-900">Canada</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">$9,120k</p>
                                <p class="text-xs text-red-600">-6.3%</p>
                                <p class="text-xs text-gray-500">320k</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img src="https://flagcdn.com/w20/au.png" alt="Australia Flag" class="w-5 h-4">
                                <span class="text-sm font-medium text-gray-900">Australia</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">$6,800k</p>
                                <p class="text-xs text-red-600">-5.0%</p>
                                <p class="text-xs text-gray-500">215k</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img src="https://flagcdn.com/w20/de.png" alt="Germany Flag" class="w-5 h-4">
                                <span class="text-sm font-medium text-gray-900">Germany</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">$7,450k</p>
                                <p class="text-xs text-red-600">-4.0%</p>
                                <p class="text-xs text-gray-500">120k</p>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Center Column -->
            <div class="space-y-8">
                
                <!-- Sales Metrics -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-building text-white text-sm"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Flyonui Company</h3>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-yellow-100 rounded-lg">
                                <i class="fas fa-chart-line text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Sales trend</p>
                                <p class="text-lg font-bold text-gray-900">$11,548</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <i class="fas fa-chart-bar text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Total Profit</p>
                                <p class="text-lg font-bold text-gray-900">$1,735</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-purple-100 rounded-lg">
                                <i class="fas fa-tag text-purple-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Discounts</p>
                                <p class="text-lg font-bold text-gray-900">$14,987</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <i class="fas fa-receipt text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Refunds</p>
                                <p class="text-lg font-bold text-gray-900">$3,248</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sales Plan -->
                <div class="bg-white rounded-lg shadow-lg p-6 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sales Plan</h3>
                    <div class="text-6xl font-bold text-blue-600 mb-2">54%</div>
                    <p class="text-sm text-gray-600">Percentage profit from total sales</p>
                </div>
                
            </div>
            
            <!-- Right Column -->
            <div class="space-y-8">
                
                <!-- Revenue Goal -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue Goal</h3>
                    <div id="revenue-goal-chart" class="flex items-center justify-center h-48"></div>
                    <div class="text-center mt-4">
                        <p class="text-sm text-gray-600">Plan Completed</p>
                        <p class="text-2xl font-bold text-blue-600">56%</p>
                    </div>
                </div>
                
                <!-- Cohort Analysis -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Cohort Analysis Indicators</h3>
                    <p class="text-sm text-gray-600 mb-4">Analyzes the behaviour of a group of users who joined a product/service at the same time, over a certain period.</p>
                    
                    <div class="flex items-center justify-between mb-4">
                        <button class="btn btn-sm btn-outline-blue flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>
                            Open Statistics
                        </button>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600" checked>
                            <span class="ml-2 text-sm text-gray-700">Percentage Change</span>
                        </label>
                    </div>
                    
                    <div class="h-64 flex items-center justify-center">
                        <canvas id="cohort-analysis-chart" width="300" height="200"></canvas>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- Payment Status Table -->
        <div class="mt-8 bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Payment Status</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">USER</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ROLE</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PLAN</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">STATUS</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face" 
                                         alt="Avatar" class="w-8 h-8 rounded-full mr-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Jordan Stevenson</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Admin</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Enterprise</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face" 
                                         alt="Avatar" class="w-8 h-8 rounded-full mr-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Emily Chen</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Subscriber</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Company</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face" 
                                         alt="Avatar" class="w-8 h-8 rounded-full mr-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Michael Johnson</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Subscriber</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Enterprise</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=32&h=32&fit=crop&crop=face" 
                                         alt="Avatar" class="w-8 h-8 rounded-full mr-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Sofia Martinez</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Author</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Team</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=32&h=32&fit=crop&crop=face" 
                                         alt="Avatar" class="w-8 h-8 rounded-full mr-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">David Kim</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Editor</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Enterprise</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Incomplete</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing 1 to 10 of 40 entries
                    </div>
                    <div class="flex items-center space-x-2">
                        <button class="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg">1</button>
                        <button class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">2</button>
                        <button class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">3</button>
                        <button class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">4</button>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Footer -->
    <div class="bg-white border-t border-gray-200 mt-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    © 2025 FlyonUI, Made with ❤️ for a better web.
                </div>
                <div class="flex items-center space-x-4 text-sm text-gray-500">
                    <a href="#" class="hover:text-gray-900">License</a>
                    <a href="#" class="hover:text-gray-900">More Themes</a>
                    <a href="#" class="hover:text-gray-900">Documentation</a>
                    <a href="#" class="hover:text-gray-900">Support</a>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Include required JavaScript libraries -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/lodash@latest/lodash.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function professionalDashboard() {
    return {
        init() {
            console.log('🎨 Professional Dashboard initialized');
            this.initCharts();
        },
        
        initCharts() {
            this.initRevenueGoalChart();
            this.initCohortAnalysisChart();
        },
        
        initRevenueGoalChart() {
            const options = {
                chart: {
                    type: 'donut',
                    height: 192,
                    width: '100%',
                    parentHeightOffset: 0
                },
                series: [14987, 1735, 11548], // Discounts, Total Profit, Sales Trend
                labels: ['Discounts', 'Total Profit', 'Sales Trend'],
                colors: ['#8b5cf6', '#f59e0b', '#10b981'], // Purple, Orange, Green
                dataLabels: {
                    enabled: false
                },
                legend: {
                    show: false
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                value: {
                                    show: true,
                                    fontSize: '1.5rem',
                                    fontWeight: 700,
                                    formatter: function (val) {
                                        return '$' + parseInt(val);
                                    }
                                },
                                name: {
                                    show: true,
                                    fontSize: '0.875rem',
                                    fontWeight: 500,
                                    offsetY: 10,
                                    formatter: function () {
                                        return 'Total Profit';
                                    }
                                },
                                total: {
                                    show: true,
                                    label: 'Total Profit',
                                    formatter: function (w) {
                                        return '$' + w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                },
                stroke: {
                    width: 0
                }
            };

            const chart = new ApexCharts(document.querySelector("#revenue-goal-chart"), options);
            chart.render();
        },
        
        initCohortAnalysisChart() {
            const canvas = document.getElementById('cohort-analysis-chart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            const chartWidth = canvas.width;
            const chartHeight = canvas.height;
            const barHeight = 25;
            const barSpacing = 15;
            const data = [100, 95, 88, 75, 60, 50, 42]; // Example retention percentages
            const labels = ['Month 0', 'Month 1', 'Month 2', 'Month 3', 'Month 4', 'Month 5', 'Month 6'];
            
            const totalBarsHeight = data.length * (barHeight + barSpacing);
            const startY = (chartHeight - totalBarsHeight) / 2 + barHeight / 2;

            ctx.clearRect(0, 0, chartWidth, chartHeight);

            data.forEach((value, index) => {
                const y = startY + index * (barHeight + barSpacing);
                const barWidth = (value / 100) * (chartWidth - 100); // Scale bar width, leave space for labels

                // Draw bar
                const gradient = ctx.createLinearGradient(0, 0, barWidth, 0);
                gradient.addColorStop(0, `rgba(139, 92, 246, ${value / 100})`); // Purple gradient
                gradient.addColorStop(1, `rgba(124, 58, 237, ${value / 100})`);
                ctx.fillStyle = gradient;
                ctx.fillRect(50, y - barHeight / 2, barWidth, barHeight);

                // Draw label (Month X)
                ctx.fillStyle = '#4B5563'; // gray-700
                ctx.font = '12px Inter, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(labels[index], 40, y + 4);

                // Draw percentage value
                ctx.textAlign = 'left';
                ctx.fillText(`${value}%`, 60 + barWidth, y + 4);
            });
        }
    };
}
</script>

<style>
/* Professional Dashboard Styles */
.btn {
    @apply px-4 py-2 rounded-lg font-medium transition-colors;
}

.btn-sm {
    @apply px-3 py-1 text-sm;
}

.btn-outline-blue {
    @apply border border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white;
}

.form-checkbox {
    @apply rounded border-gray-300 text-blue-600 focus:ring-blue-500;
}

/* Custom scrollbar */
.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        @apply grid-cols-1;
    }
    
    .lg\\:grid-cols-3 {
        @apply grid-cols-1;
    }
    
    .grid-cols-2 {
        @apply grid-cols-1;
    }
}

/* Focus states for accessibility */
button:focus,
input:focus,
select:focus {
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
    .transition-colors {
        transition: none;
    }
}
</style>
@endsection
