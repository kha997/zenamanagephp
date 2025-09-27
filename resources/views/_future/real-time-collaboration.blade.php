<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Collaboration - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Real-time collaborative dashboard editing for ZenaManage">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZenaManage">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
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
        .collaboration-card {
            transition: all 0.3s ease;
        }
        .collaboration-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .user-cursor {
            position: absolute;
            pointer-events: none;
            z-index: 1000;
            transition: all 0.1s ease;
        }
        .user-presence {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .comment-bubble {
            position: absolute;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 1001;
            max-width: 200px;
        }
        .version-timeline {
            position: relative;
        }
        .version-timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        .collaboration-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased">
    <div x-data="realTimeCollaboration()" x-init="init()" class="min-h-screen">
        <!-- Collaboration Indicator -->
        <div class="collaboration-indicator">
            <div class="bg-white rounded-lg shadow-lg p-4">
                <div class="flex items-center space-x-3">
                    <div class="flex -space-x-2">
                        <template x-for="user in activeUsers" :key="user.id">
                            <div class="relative">
                                <img :src="user.avatar" 
                                     :alt="user.name"
                                     class="w-8 h-8 rounded-full border-2 border-white"
                                     :title="user.name + ' is editing'">
                                <div class="absolute -bottom-1 -right-1 w-3 h-3 rounded-full user-presence"
                                     :style="`background-color: ${user.color}`"></div>
                            </div>
                        </template>
                    </div>
                    <div class="text-sm">
                        <div class="font-medium text-gray-900" x-text="`${activeUsers.length} user${activeUsers.length !== 1 ? 's' : ''} editing`"></div>
                        <div class="text-gray-500" x-text="collaborationMode ? 'Live collaboration' : 'View only'"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-users text-blue-600 mr-2"></i>
                                Real-time Collaboration
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Collaboration Controls -->
                    <div class="flex items-center space-x-4">
                        <!-- Collaboration Mode Toggle -->
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600">Collaboration Mode:</span>
                            <button @click="toggleCollaborationMode()" 
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="collaborationMode ? 'bg-blue-600' : 'bg-gray-200'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                      :class="collaborationMode ? 'translate-x-6' : 'translate-x-1'"></span>
                            </button>
                        </div>
                        
                        <!-- User Count -->
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-users text-gray-400"></i>
                            <span class="text-sm text-gray-600" x-text="`${activeUsers.length} active`"></span>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex items-center space-x-3">
                            <button @click="goToBuilder()" 
                                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                <i class="fas fa-cog mr-2"></i>Dashboard Builder
                            </button>
                            <button @click="goToDataSources()" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-database mr-2"></i>Data Sources
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Collaboration Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Users</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="activeUsers.length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-comments text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Comments</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="comments.length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-history text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Versions</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="versions.length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <i class="fas fa-sync-alt text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Changes</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="totalChanges"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Collaboration Features -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Multi-user Editing -->
                <div class="collaboration-card bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-blue-100 rounded-lg mr-4">
                            <i class="fas fa-edit text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Multi-user Editing</h3>
                            <p class="text-sm text-gray-600">Real-time collaborative dashboard editing</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- User Cursors -->
                        <div class="relative h-32 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300" 
                             @mousemove="updateCursorPosition($event)"
                             @mouseleave="hideCursor()">
                            <div class="absolute inset-0 flex items-center justify-center text-gray-500">
                                <div class="text-center">
                                    <i class="fas fa-mouse-pointer text-2xl mb-2"></i>
                                    <p class="text-sm">Move your mouse to see cursor sharing</p>
                                </div>
                            </div>
                            
                            <!-- User Cursors -->
                            <template x-for="user in activeUsers" :key="user.id">
                                <div class="user-cursor" 
                                     :style="`left: ${user.cursor.x}px; top: ${user.cursor.y}px; color: ${user.color}`"
                                     x-show="user.cursor.visible">
                                    <i class="fas fa-mouse-pointer text-lg"></i>
                                    <div class="text-xs font-medium mt-1" x-text="user.name"></div>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Collaboration Controls -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <button @click="inviteUser()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-user-plus mr-2"></i>Invite User
                                </button>
                                <button @click="shareDashboard()" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    <i class="fas fa-share mr-2"></i>Share Dashboard
                                </button>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">Auto-save:</span>
                                <div class="w-3 h-3 rounded-full" 
                                     :class="autoSave ? 'bg-green-500' : 'bg-red-500'"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Presence -->
                <div class="collaboration-card bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-green-100 rounded-lg mr-4">
                            <i class="fas fa-eye text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">User Presence</h3>
                            <p class="text-sm text-gray-600">See who's online and what they're doing</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <template x-for="user in activeUsers" :key="user.id">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="relative">
                                        <img :src="user.avatar" 
                                             :alt="user.name"
                                             class="w-10 h-10 rounded-full">
                                        <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full user-presence"
                                             :style="`background-color: ${user.color}`"></div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900" x-text="user.name"></p>
                                        <p class="text-xs text-gray-500" x-text="user.status"></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs px-2 py-1 rounded-full"
                                          :class="getStatusBadgeClass(user.status)"
                                          x-text="user.status"></span>
                                    <button @click="sendMessage(user)" 
                                            class="p-1 text-gray-400 hover:text-blue-600">
                                        <i class="fas fa-comment"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Comments & Discussions -->
            <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Comments & Discussions</h3>
                    <button @click="showAddComment = true" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Comment
                    </button>
                </div>
                
                <div class="space-y-4">
                    <template x-for="comment in comments" :key="comment.id">
                        <div class="flex items-start space-x-3 p-4 bg-gray-50 rounded-lg">
                            <img :src="comment.author.avatar" 
                                 :alt="comment.author.name"
                                 class="w-8 h-8 rounded-full">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-1">
                                    <span class="text-sm font-medium text-gray-900" x-text="comment.author.name"></span>
                                    <span class="text-xs text-gray-500" x-text="comment.timestamp"></span>
                                </div>
                                <p class="text-sm text-gray-700" x-text="comment.content"></p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <button @click="replyToComment(comment)" 
                                            class="text-xs text-blue-600 hover:text-blue-800">
                                        Reply
                                    </button>
                                    <button @click="resolveComment(comment)" 
                                            class="text-xs text-green-600 hover:text-green-800">
                                        Resolve
                                    </button>
                                    <button @click="deleteComment(comment)" 
                                            class="text-xs text-red-600 hover:text-red-800">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Version Control -->
            <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Version Control</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="createVersion()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>Save Version
                        </button>
                        <button @click="showVersionHistory = true" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-history mr-2"></i>View History
                        </button>
                    </div>
                </div>
                
                <div class="version-timeline">
                    <template x-for="version in versions" :key="version.id">
                        <div class="relative flex items-start space-x-4 pb-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-save text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-medium text-gray-900" x-text="version.name"></h4>
                                    <span class="text-xs text-gray-500" x-text="version.timestamp"></span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1" x-text="version.description"></p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <button @click="restoreVersion(version)" 
                                            class="text-xs text-blue-600 hover:text-blue-800">
                                        Restore
                                    </button>
                                    <button @click="compareVersion(version)" 
                                            class="text-xs text-green-600 hover:text-green-800">
                                        Compare
                                    </button>
                                    <button @click="deleteVersion(version)" 
                                            class="text-xs text-red-600 hover:text-red-800">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Change Tracking -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Change Tracking</h3>
                
                <div class="space-y-3">
                    <template x-for="change in recentChanges" :key="change.id">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     :class="getChangeTypeClass(change.type)">
                                    <i :class="getChangeTypeIcon(change.type)" 
                                       :class="getChangeTypeIconColor(change.type)"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="change.description"></p>
                                    <p class="text-xs text-gray-500" x-text="`by ${change.author.name} • ${change.timestamp}`"></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button @click="undoChange(change)" 
                                        class="text-xs text-blue-600 hover:text-blue-800">
                                    Undo
                                </button>
                                <button @click="viewChange(change)" 
                                        class="text-xs text-green-600 hover:text-green-800">
                                    View
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>

        <!-- Add Comment Modal -->
        <div x-show="showAddComment" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
             @click="showAddComment = false">
            <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900">Add Comment</h3>
                        <button @click="showAddComment = false" 
                                class="p-2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <form @submit.prevent="addComment()">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Comment</label>
                                <textarea x-model="newComment.content"
                                          rows="4"
                                          required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select x-model="newComment.priority"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200">
                    <div class="flex items-center justify-end space-x-3">
                        <button @click="showAddComment = false" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="addComment()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Comment
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Version History Modal -->
        <div x-show="showVersionHistory" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
             @click="showVersionHistory = false">
            <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900">Version History</h3>
                        <button @click="showVersionHistory = false" 
                                class="p-2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div class="version-timeline">
                        <template x-for="version in versions" :key="version.id">
                            <div class="relative flex items-start space-x-4 pb-6">
                                <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-save text-blue-600"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-lg font-medium text-gray-900" x-text="version.name"></h4>
                                        <span class="text-sm text-gray-500" x-text="version.timestamp"></span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3" x-text="version.description"></p>
                                    <div class="flex items-center space-x-4">
                                        <button @click="restoreVersion(version)" 
                                                class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors">
                                            Restore
                                        </button>
                                        <button @click="compareVersion(version)" 
                                                class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors">
                                            Compare
                                        </button>
                                        <button @click="downloadVersion(version)" 
                                                class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700 transition-colors">
                                            Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200">
                    <div class="flex items-center justify-end">
                        <button @click="showVersionHistory = false" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function realTimeCollaboration() {
            return {
                // State
                collaborationMode: true,
                activeUsers: [],
                comments: [],
                versions: [],
                recentChanges: [],
                totalChanges: 0,
                autoSave: true,
                showAddComment: false,
                showVersionHistory: false,
                newComment: {
                    content: '',
                    priority: 'medium'
                },

                // Initialize
                init() {
                    this.loadActiveUsers();
                    this.loadComments();
                    this.loadVersions();
                    this.loadRecentChanges();
                    this.startCollaboration();
                },

                // Load Active Users
                loadActiveUsers() {
                    this.activeUsers = [
                        {
                            id: 1,
                            name: 'John Smith',
                            avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face',
                            status: 'editing',
                            color: '#3b82f6',
                            cursor: { x: 0, y: 0, visible: false }
                        },
                        {
                            id: 2,
                            name: 'Sarah Johnson',
                            avatar: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face',
                            status: 'viewing',
                            color: '#10b981',
                            cursor: { x: 0, y: 0, visible: false }
                        },
                        {
                            id: 3,
                            name: 'Mike Chen',
                            avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face',
                            status: 'commenting',
                            color: '#f59e0b',
                            cursor: { x: 0, y: 0, visible: false }
                        }
                    ];
                },

                // Load Comments
                loadComments() {
                    this.comments = [
                        {
                            id: 1,
                            content: 'This chart needs to be updated with the latest Q4 data',
                            author: {
                                name: 'John Smith',
                                avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face'
                            },
                            timestamp: '2 minutes ago',
                            priority: 'high',
                            resolved: false
                        },
                        {
                            id: 2,
                            content: 'Great work on the mobile responsiveness!',
                            author: {
                                name: 'Sarah Johnson',
                                avatar: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face'
                            },
                            timestamp: '5 minutes ago',
                            priority: 'low',
                            resolved: true
                        },
                        {
                            id: 3,
                            content: 'Can we add more KPIs to the dashboard?',
                            author: {
                                name: 'Mike Chen',
                                avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face'
                            },
                            timestamp: '10 minutes ago',
                            priority: 'medium',
                            resolved: false
                        }
                    ];
                },

                // Load Versions
                loadVersions() {
                    this.versions = [
                        {
                            id: 1,
                            name: 'v1.2.0',
                            description: 'Added new KPI widgets and improved performance',
                            timestamp: '1 hour ago',
                            author: 'John Smith',
                            changes: 15
                        },
                        {
                            id: 2,
                            name: 'v1.1.5',
                            description: 'Fixed mobile responsiveness issues',
                            timestamp: '3 hours ago',
                            author: 'Sarah Johnson',
                            changes: 8
                        },
                        {
                            id: 3,
                            name: 'v1.1.0',
                            description: 'Initial dashboard setup with basic widgets',
                            timestamp: '1 day ago',
                            author: 'Mike Chen',
                            changes: 25
                        }
                    ];
                },

                // Load Recent Changes
                loadRecentChanges() {
                    this.recentChanges = [
                        {
                            id: 1,
                            type: 'add',
                            description: 'Added new KPI widget',
                            author: {
                                name: 'John Smith',
                                avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face'
                            },
                            timestamp: '2 minutes ago'
                        },
                        {
                            id: 2,
                            type: 'edit',
                            description: 'Updated chart configuration',
                            author: {
                                name: 'Sarah Johnson',
                                avatar: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face'
                            },
                            timestamp: '5 minutes ago'
                        },
                        {
                            id: 3,
                            type: 'delete',
                            description: 'Removed unused widget',
                            author: {
                                name: 'Mike Chen',
                                avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face'
                            },
                            timestamp: '8 minutes ago'
                        }
                    ];
                    
                    this.totalChanges = this.recentChanges.length;
                },

                // Start Collaboration
                startCollaboration() {
                    // Simulate real-time updates
                    setInterval(() => {
                        this.updateUserCursors();
                    }, 100);
                    
                    // Simulate auto-save
                    if (this.autoSave) {
                        setInterval(() => {
                            this.autoSaveDashboard();
                        }, 30000); // Auto-save every 30 seconds
                    }
                },

                // Update User Cursors
                updateUserCursors() {
                    this.activeUsers.forEach(user => {
                        if (user.status === 'editing') {
                            // Simulate cursor movement
                            user.cursor.x += (Math.random() - 0.5) * 10;
                            user.cursor.y += (Math.random() - 0.5) * 10;
                            user.cursor.visible = true;
                        }
                    });
                },

                // Update Cursor Position
                updateCursorPosition(event) {
                    if (this.collaborationMode) {
                        // Update current user cursor
                        const currentUser = this.activeUsers[0]; // Assume current user is first
                        currentUser.cursor.x = event.clientX;
                        currentUser.cursor.y = event.clientY;
                        currentUser.cursor.visible = true;
                    }
                },

                // Hide Cursor
                hideCursor() {
                    const currentUser = this.activeUsers[0];
                    currentUser.cursor.visible = false;
                },

                // Toggle Collaboration Mode
                toggleCollaborationMode() {
                    this.collaborationMode = !this.collaborationMode;
                    if (!this.collaborationMode) {
                        this.activeUsers.forEach(user => {
                            user.cursor.visible = false;
                        });
                    }
                },

                // Invite User
                inviteUser() {
                    const email = prompt('Enter user email to invite:');
                    if (email) {
                        alert(`Invitation sent to ${email}`);
                    }
                },

                // Share Dashboard
                shareDashboard() {
                    const url = window.location.href;
                    navigator.clipboard.writeText(url);
                    alert('Dashboard link copied to clipboard!');
                },

                // Send Message
                sendMessage(user) {
                    const message = prompt(`Send message to ${user.name}:`);
                    if (message) {
                        alert(`Message sent to ${user.name}: ${message}`);
                    }
                },

                // Add Comment
                addComment() {
                    const comment = {
                        id: Date.now(),
                        content: this.newComment.content,
                        author: {
                            name: 'You',
                            avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face'
                        },
                        timestamp: 'Just now',
                        priority: this.newComment.priority,
                        resolved: false
                    };
                    
                    this.comments.unshift(comment);
                    this.showAddComment = false;
                    this.newComment = { content: '', priority: 'medium' };
                    
                    alert('Comment added successfully!');
                },

                // Reply to Comment
                replyToComment(comment) {
                    const reply = prompt(`Reply to ${comment.author.name}:`);
                    if (reply) {
                        alert(`Reply sent: ${reply}`);
                    }
                },

                // Resolve Comment
                resolveComment(comment) {
                    comment.resolved = true;
                    alert('Comment marked as resolved!');
                },

                // Delete Comment
                deleteComment(comment) {
                    if (confirm('Are you sure you want to delete this comment?')) {
                        this.comments = this.comments.filter(c => c.id !== comment.id);
                        alert('Comment deleted successfully!');
                    }
                },

                // Create Version
                createVersion() {
                    const name = prompt('Enter version name:');
                    if (name) {
                        const version = {
                            id: Date.now(),
                            name: name,
                            description: 'Manual save point',
                            timestamp: 'Just now',
                            author: 'You',
                            changes: this.totalChanges
                        };
                        
                        this.versions.unshift(version);
                        alert(`Version ${name} created successfully!`);
                    }
                },

                // Restore Version
                restoreVersion(version) {
                    if (confirm(`Are you sure you want to restore version ${version.name}?`)) {
                        alert(`Version ${version.name} restored successfully!`);
                    }
                },

                // Compare Version
                compareVersion(version) {
                    alert(`Comparing with version ${version.name}...`);
                },

                // Download Version
                downloadVersion(version) {
                    alert(`Downloading version ${version.name}...`);
                },

                // Delete Version
                deleteVersion(version) {
                    if (confirm(`Are you sure you want to delete version ${version.name}?`)) {
                        this.versions = this.versions.filter(v => v.id !== version.id);
                        alert(`Version ${version.name} deleted successfully!`);
                    }
                },

                // Undo Change
                undoChange(change) {
                    if (confirm(`Are you sure you want to undo: ${change.description}?`)) {
                        this.recentChanges = this.recentChanges.filter(c => c.id !== change.id);
                        this.totalChanges--;
                        alert('Change undone successfully!');
                    }
                },

                // View Change
                viewChange(change) {
                    alert(`Viewing change: ${change.description}`);
                },

                // Auto Save Dashboard
                autoSaveDashboard() {
                    if (this.autoSave) {
                        console.log('Auto-saving dashboard...');
                        // Simulate auto-save
                    }
                },

                // Utility Methods
                getStatusBadgeClass(status) {
                    const classes = {
                        editing: 'bg-blue-100 text-blue-800',
                        viewing: 'bg-green-100 text-green-800',
                        commenting: 'bg-yellow-100 text-yellow-800'
                    };
                    return classes[status] || 'bg-gray-100 text-gray-800';
                },

                getChangeTypeClass(type) {
                    const classes = {
                        add: 'bg-green-100',
                        edit: 'bg-blue-100',
                        delete: 'bg-red-100'
                    };
                    return classes[type] || 'bg-gray-100';
                },

                getChangeTypeIcon(type) {
                    const icons = {
                        add: 'fas fa-plus',
                        edit: 'fas fa-edit',
                        delete: 'fas fa-trash'
                    };
                    return icons[type] || 'fas fa-question';
                },

                getChangeTypeIconColor(type) {
                    const colors = {
                        add: 'text-green-600',
                        edit: 'text-blue-600',
                        delete: 'text-red-600'
                    };
                    return colors[type] || 'text-gray-600';
                },

                // Navigation
                goToBuilder() {
                    window.location.href = '/app/dashboard-builder';
                },

                goToDataSources() {
                    window.location.href = '/app/advanced-data-sources';
                }
            };
        }
    </script>
</body>
</html>
