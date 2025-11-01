@extends('layouts.project-detail')

@section('title', 'Design Project - Office Building Complex')
@section('page-title', 'Office Building Complex')
@section('page-description', 'Modern office building design project with 20 floors')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')

@section('content')
<div x-data="designProject()">
    <!-- Overview Tab -->
    <div x-show="activeTab === 'overview'">
        <!-- Project Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="dashboard-card metric-card blue p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Design Progress</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.designProgress + '%'"></p>
                        <p class="text-white/80 text-sm">+5% this week</p>
                    </div>
                    <i class="fas fa-palette text-4xl text-white/60"></i>
                </div>
            </div>

            <div class="dashboard-card metric-card green p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Active Tasks</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.activeTasks"></p>
                        <p class="text-white/80 text-sm">8 in progress</p>
                    </div>
                    <i class="fas fa-tasks text-4xl text-white/60"></i>
                </div>
            </div>

            <div class="dashboard-card metric-card orange p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Team Members</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.teamMembers"></p>
                        <p class="text-white/80 text-sm">Design team</p>
                    </div>
                    <i class="fas fa-users text-4xl text-white/60"></i>
                </div>
            </div>

            <div class="dashboard-card metric-card purple p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Documents</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.documents"></p>
                        <p class="text-white/80 text-sm">Design files</p>
                    </div>
                    <i class="fas fa-file-alt text-4xl text-white/60"></i>
                </div>
            </div>
        </div>

        <!-- Project Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Project Info -->
            <div class="lg:col-span-2">
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Project Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Project Type</label>
                            <p class="text-gray-900">Design Project</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Start Date</label>
                            <p class="text-gray-900">Jan 15, 2024</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Due Date</label>
                            <p class="text-gray-900">Mar 15, 2024</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Client</label>
                            <p class="text-gray-900">ABC Corporation</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Budget</label>
                            <p class="text-gray-900">$2,500,000</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="text-sm font-medium text-gray-500 mb-2 block">Description</label>
                        <p class="text-gray-700">Modern office building design with 20 floors, featuring advanced facilities, sustainable design elements, and state-of-the-art technology integration. The project includes architectural design, interior design, and MEP systems.</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div>
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">⚡ Quick Actions</h3>
                    <div class="space-y-3">
                        <button 
                            @click="createTask()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-plus mr-2"></i>Create Task
                        </button>
                        <button 
                            @click="uploadDocument()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-upload mr-2"></i>Upload Document
                        </button>
                        <button 
                            @click="inviteTeamMember()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-user-plus mr-2"></i>Invite Team Member
                        </button>
                        <button 
                            @click="scheduleMeeting()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-calendar mr-2"></i>Schedule Meeting
                        </button>
                        <button 
                            @click="generateReport()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-chart-bar mr-2"></i>Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Design Progress -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="dashboard-card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">🎨 Design Progress</h3>
                    <button 
                        @click="addDesignPhase()"
                        class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors"
                    >
                        <i class="fas fa-plus mr-1"></i>Add Phase
                    </button>
                </div>
                <div class="space-y-4">
                    <template x-for="designPhase in designPhases" :key="designPhase.id">
                        <div class="border border-gray-200 rounded-lg p-3 hover:border-gray-300 transition-colors">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex-1">
                                    <!-- Editable Phase Name -->
                                    <div x-show="!designPhase.editing">
                                        <span 
                                            class="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600"
                                            @click="editPhase(designPhase)"
                                            x-text="designPhase.name"
                                        ></span>
                                    </div>
                                    <div x-show="designPhase.editing" class="flex items-center space-x-2">
                                        <input 
                                            type="text" 
                                            x-model="designPhase.name"
                                            :data-phase-id="designPhase.id"
                                            class="text-sm font-medium text-gray-900 border border-blue-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            @keyup.enter="savePhase(designPhase)"
                                            @keyup.escape="cancelEdit(designPhase)"
                                        >
                                        <button 
                                            @click="savePhase(designPhase)"
                                            class="text-green-600 hover:text-green-800"
                                        >
                                            <i class="fas fa-check text-xs"></i>
                                        </button>
                                        <button 
                                            @click="cancelEdit(designPhase)"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600" x-text="designPhase.progress + '%'"></span>
                                    <div class="flex space-x-1">
                                        <button 
                                            @click="editPhase(designPhase)"
                                            class="p-1 text-gray-400 hover:text-blue-600"
                                            x-show="!designPhase.editing"
                                        >
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button 
                                            @click="deletePhase(designPhase)"
                                            class="p-1 text-gray-400 hover:text-red-600"
                                            x-show="!designPhase.editing"
                                        >
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                <div 
                                    class="h-2 rounded-full transition-all duration-500"
                                    :class="designPhase.colorClass"
                                    :style="`width: ${designPhase.progress}%`"
                                ></div>
                            </div>
                            <div class="flex justify-between items-center text-xs text-gray-500">
                                <div class="flex items-center space-x-2">
                                    <span x-text="designPhase.status"></span>
                                    <select 
                                        x-model="designPhase.status"
                                        class="text-xs border border-gray-300 rounded px-1 py-0.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        @change="updatePhaseStatus(designPhase)"
                                    >
                                        <option value="Planning">Planning</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Review">Review</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span x-text="designPhase.dueDate"></span>
                                    <input 
                                        type="date" 
                                        x-model="designPhase.dueDate"
                                        class="text-xs border border-gray-300 rounded px-1 py-0.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        @change="updatePhaseDueDate(designPhase)"
                                    >
                                </div>
                            </div>
                            <div class="mt-2 flex items-center space-x-2">
                                <span class="text-xs text-gray-500">Progress:</span>
                                <input 
                                    type="range" 
                                    min="0" 
                                    max="100" 
                                    x-model="designPhase.progress"
                                    class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                    @input="updatePhaseProgress(designPhase)"
                                >
                                <span class="text-xs font-medium text-gray-700" x-text="designPhase.progress + '%'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="dashboard-card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📅 Upcoming Milestones</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900">Final Architectural Review</p>
                            <p class="text-sm text-gray-600">Feb 15, 2024</p>
                        </div>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Upcoming</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900">Interior Design Approval</p>
                            <p class="text-sm text-gray-600">Feb 28, 2024</p>
                        </div>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">In Progress</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900">MEP Design Complete</p>
                            <p class="text-sm text-gray-600">Mar 5, 2024</p>
                        </div>
                        <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">Pending</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Gallery -->
        <div class="dashboard-card p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">🖼️ Project Gallery</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="aspect-square bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">🏢</div>
                        <div class="text-sm font-medium text-blue-800">Building Exterior</div>
                    </div>
                </div>
                <div class="aspect-square bg-gradient-to-br from-green-100 to-green-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">🏛️</div>
                        <div class="text-sm font-medium text-green-800">Lobby Design</div>
                    </div>
                </div>
                <div class="aspect-square bg-gradient-to-br from-purple-100 to-purple-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">🏗️</div>
                        <div class="text-sm font-medium text-purple-800">3D Render</div>
                    </div>
                </div>
                <div class="aspect-square bg-gradient-to-br from-orange-100 to-orange-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">🌿</div>
                        <div class="text-sm font-medium text-orange-800">Rooftop Garden</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📈 Recent Activities</h3>
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-palette text-blue-600 text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Design review completed for Floor 15-20</p>
                        <p class="text-sm text-gray-600">Sarah Wilson updated the architectural plans</p>
                        <p class="text-xs text-gray-500">2 hours ago</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check text-green-600 text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Task completed: Interior design for lobby</p>
                        <p class="text-sm text-gray-600">Mike Johnson finished the lobby design</p>
                        <p class="text-xs text-gray-500">4 hours ago</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-file-alt text-orange-600 text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">New document uploaded: MEP specifications</p>
                        <p class="text-sm text-gray-600">Alex Lee uploaded MEP design documents</p>
                        <p class="text-xs text-gray-500">1 day ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tasks Tab -->
    <div x-show="activeTab === 'tasks'">
        <div class="dashboard-card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">📝 Project Tasks</h3>
                <button 
                    @click="createTask()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <i class="fas fa-plus mr-2"></i>Create Task
                </button>
            </div>
            
            <div class="space-y-4">
                <template x-for="task in projectTasks" :key="task.id">
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h4 class="font-medium text-gray-900" x-text="task.title"></h4>
                                    <span 
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="getTaskStatusClass(task.status)"
                                        x-text="task.status"
                                    ></span>
                                    <span 
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="getTaskPriorityClass(task.priority)"
                                        x-text="task.priority"
                                    ></span>
                                </div>
                                <p class="text-sm text-gray-600 mb-2" x-text="task.description"></p>
                                <div class="flex items-center space-x-4 text-xs text-gray-500">
                                    <span><i class="fas fa-user mr-1"></i><span x-text="task.assignee"></span></span>
                                    <span><i class="fas fa-calendar mr-1"></i><span x-text="task.dueDate"></span></span>
                                    <span><i class="fas fa-clock mr-1"></i><span x-text="task.estimatedHours + 'h'"></span></span>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button class="p-1 text-gray-400 hover:text-blue-600">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="p-1 text-gray-400 hover:text-red-600">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Team Tab -->
    <div x-show="activeTab === 'team'">
        <div class="dashboard-card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">👥 Project Team</h3>
                <button 
                    @click="inviteTeamMember()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <i class="fas fa-user-plus mr-2"></i>Invite Member
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="member in projectTeam" :key="member.id">
                    <div class="border border-gray-200 rounded-lg p-4 text-center">
                        <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-xl mx-auto mb-3">
                            <span x-text="member.initials"></span>
                        </div>
                        <h4 class="font-medium text-gray-900" x-text="member.name"></h4>
                        <p class="text-sm text-gray-600 mb-2" x-text="member.role"></p>
                        <p class="text-xs text-gray-500 mb-3" x-text="member.department"></p>
                        <div class="flex justify-center space-x-2">
                            <button class="p-1 text-gray-400 hover:text-blue-600">
                                <i class="fas fa-envelope"></i>
                            </button>
                            <button class="p-1 text-gray-400 hover:text-green-600">
                                <i class="fas fa-phone"></i>
                            </button>
                            <button class="p-1 text-gray-400 hover:text-purple-600">
                                <i class="fas fa-user-cog"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Documents Tab -->
    <div x-show="activeTab === 'documents'">
        <div class="dashboard-card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">📄 Project Documents</h3>
                <button 
                    @click="uploadDocument()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <i class="fas fa-upload mr-2"></i>Upload Document
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="document in projectDocuments" :key="document.id">
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer">
                        <div class="flex items-start justify-between mb-3">
                            <div class="text-3xl" x-text="getDocumentIcon(document.type)"></div>
                            <div class="flex space-x-1">
                                <button class="p-1 text-gray-400 hover:text-blue-600">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="p-1 text-gray-400 hover:text-red-600">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <h4 class="font-medium text-gray-900 mb-1" x-text="document.title"></h4>
                        <p class="text-sm text-gray-600 mb-2" x-text="document.description"></p>
                        <div class="text-xs text-gray-500 space-y-1">
                            <div class="flex justify-between">
                                <span>Size:</span>
                                <span x-text="document.size"></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Uploaded:</span>
                                <span x-text="document.uploadedAt"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Timeline Tab -->
    <div x-show="activeTab === 'timeline'">
        <!-- Gantt Chart -->
        <div class="dashboard-card p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">📊 Gantt Chart</h3>
                <div class="flex space-x-2">
                    <button 
                        @click="ganttView = 'month'"
                        :class="ganttView === 'month' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-3 py-1 rounded text-sm transition-colors"
                    >
                        Month View
                    </button>
                    <button 
                        @click="ganttView = 'week'"
                        :class="ganttView === 'week' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-3 py-1 rounded text-sm transition-colors"
                    >
                        Week View
                    </button>
                </div>
            </div>
            
            <!-- Gantt Chart Container -->
            <div class="overflow-x-auto">
                <div class="min-w-full">
                    <!-- Header -->
                    <div class="flex border-b border-gray-200 mb-4">
                        <div class="w-48 px-4 py-2 font-medium text-gray-900">Design Phase</div>
                        <div class="flex-1 px-4 py-2 font-medium text-gray-900 text-center">Timeline</div>
                        <div class="w-20 px-4 py-2 font-medium text-gray-900 text-center">Progress</div>
                    </div>
                    
                    <!-- Gantt Rows -->
                    <template x-for="phase in designPhases" :key="phase.id">
                        <div class="flex items-center border-b border-gray-100 py-3">
                            <div class="w-48 px-4">
                                <div class="font-medium text-gray-900" x-text="phase.name"></div>
                                <div class="text-sm text-gray-500" x-text="phase.status"></div>
                            </div>
                            <div class="flex-1 px-4">
                                <div class="relative h-8 bg-gray-100 rounded">
                                    <!-- Progress Bar -->
                                    <div 
                                        class="absolute top-0 left-0 h-full rounded transition-all duration-500"
                                        :class="phase.colorClass"
                                        :style="`width: ${phase.progress}%`"
                                    ></div>
                                    <!-- Progress Text -->
                                    <div class="absolute inset-0 flex items-center justify-center text-xs font-medium text-white">
                                        <span x-text="phase.progress + '%'"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="w-20 px-4 text-center">
                                <span class="text-sm font-medium text-gray-900" x-text="phase.progress + '%'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Milestones -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">📅 Project Milestones</h3>
            <div class="space-y-6">
                <template x-for="milestone in projectTimeline" :key="milestone.id">
                    <div class="flex items-start space-x-4">
                        <div class="w-4 h-4 bg-blue-600 rounded-full mt-2"></div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="font-medium text-gray-900" x-text="milestone.title"></h4>
                                <span class="text-sm text-gray-500" x-text="milestone.date"></span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1" x-text="milestone.description"></p>
                            <div class="mt-2">
                                <span 
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getMilestoneStatusClass(milestone.status)"
                                    x-text="milestone.status"
                                ></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Settings Tab -->
    <div x-show="activeTab === 'settings'">
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">⚙️ Project Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-4">General Settings</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Name</label>
                            <input type="text" value="Office Building Complex" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Description</label>
                            <textarea rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">Modern office building design with 20 floors</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Status</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="active">Active</option>
                                <option value="planning">Planning</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-4">Notification Settings</h4>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" checked class="mr-2">
                            <span class="text-sm">Email notifications</span>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" checked class="mr-2">
                            <span class="text-sm">Task assignments</span>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" class="mr-2">
                            <span class="text-sm">Document updates</span>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" checked class="mr-2">
                            <span class="text-sm">Milestone alerts</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function designProject() {
    return {
        ganttView: 'month',
        
        projectStats: {
            designProgress: 78,
            activeTasks: 15,
            teamMembers: 8,
            documents: 32
        },
        
        designPhases: [
            {
                id: 1,
                name: 'Architectural Design',
                progress: 88,
                status: 'In Progress',
                dueDate: 'Feb 15, 2024',
                colorClass: 'bg-blue-500',
                editing: false,
                originalName: 'Architectural Design'
            },
            {
                id: 2,
                name: 'Interior Design',
                progress: 72,
                status: 'In Progress',
                dueDate: 'Feb 28, 2024',
                colorClass: 'bg-green-500',
                editing: false,
                originalName: 'Interior Design'
            },
            {
                id: 3,
                name: 'MEP Systems',
                progress: 65,
                status: 'In Progress',
                dueDate: 'Mar 5, 2024',
                colorClass: 'bg-orange-500',
                editing: false,
                originalName: 'MEP Systems'
            },
            {
                id: 4,
                name: '3D Visualization',
                progress: 45,
                status: 'Planning',
                dueDate: 'Mar 15, 2024',
                colorClass: 'bg-purple-500',
                editing: false,
                originalName: '3D Visualization'
            },
            {
                id: 5,
                name: 'Landscape Design',
                progress: 35,
                status: 'Planning',
                dueDate: 'Mar 20, 2024',
                colorClass: 'bg-teal-500',
                editing: false,
                originalName: 'Landscape Design'
            },
            {
                id: 6,
                name: 'Structural Design',
                progress: 82,
                status: 'In Progress',
                dueDate: 'Feb 25, 2024',
                colorClass: 'bg-red-500',
                editing: false,
                originalName: 'Structural Design'
            }
        ],
        
        projectTasks: [
            {
                id: 1,
                title: 'Architectural Design - Floor 15-20',
                description: 'Complete architectural design for upper floors including structural elements and facade design',
                status: 'in_progress',
                priority: 'high',
                assignee: 'Sarah Wilson',
                dueDate: 'Feb 15, 2024',
                estimatedHours: 40
            },
            {
                id: 2,
                title: 'Interior Design - Executive Floor',
                description: 'Design luxury executive offices with premium finishes and modern amenities',
                status: 'completed',
                priority: 'high',
                assignee: 'Mike Johnson',
                dueDate: 'Feb 10, 2024',
                estimatedHours: 32
            },
            {
                id: 3,
                title: 'MEP Design - HVAC Systems',
                description: 'Design comprehensive HVAC systems for all floors with energy efficiency focus',
                status: 'in_progress',
                priority: 'high',
                assignee: 'Alex Lee',
                dueDate: 'Mar 5, 2024',
                estimatedHours: 48
            },
            {
                id: 4,
                title: '3D Visualization - Lobby Area',
                description: 'Create photorealistic 3D renders of the main lobby and reception area',
                status: 'todo',
                priority: 'medium',
                assignee: 'Emma Davis',
                dueDate: 'Feb 25, 2024',
                estimatedHours: 24
            },
            {
                id: 5,
                title: 'Lighting Design - Conference Rooms',
                description: 'Design smart lighting systems for conference rooms and meeting spaces',
                status: 'todo',
                priority: 'medium',
                assignee: 'David Chen',
                dueDate: 'Mar 1, 2024',
                estimatedHours: 16
            },
            {
                id: 6,
                title: 'Landscape Design - Rooftop Garden',
                description: 'Design rooftop garden with sustainable landscaping and outdoor seating',
                status: 'review',
                priority: 'low',
                assignee: 'Lisa Wang',
                dueDate: 'Mar 10, 2024',
                estimatedHours: 20
            }
        ],
        
        projectTeam: [
            {
                id: 1,
                name: 'Sarah Wilson',
                role: 'Lead Architect',
                department: 'Architecture',
                initials: 'SW'
            },
            {
                id: 2,
                name: 'Mike Johnson',
                role: 'Senior Interior Designer',
                department: 'Interior Design',
                initials: 'MJ'
            },
            {
                id: 3,
                name: 'Alex Lee',
                role: 'MEP Engineer',
                department: 'Engineering',
                initials: 'AL'
            },
            {
                id: 4,
                name: 'Emma Davis',
                role: '3D Visualization Specialist',
                department: 'Visualization',
                initials: 'ED'
            },
            {
                id: 5,
                name: 'David Chen',
                role: 'Lighting Designer',
                department: 'Lighting',
                initials: 'DC'
            },
            {
                id: 6,
                name: 'Lisa Wang',
                role: 'Landscape Architect',
                department: 'Landscape',
                initials: 'LW'
            },
            {
                id: 7,
                name: 'James Rodriguez',
                role: 'Structural Engineer',
                department: 'Engineering',
                initials: 'JR'
            },
            {
                id: 8,
                name: 'Anna Thompson',
                role: 'Project Coordinator',
                department: 'Management',
                initials: 'AT'
            }
        ],
        
        projectDocuments: [
            {
                id: 1,
                title: 'Architectural Plans - Floor 1-10',
                description: 'Detailed architectural drawings with structural elements',
                type: 'drawing',
                size: '2.4 MB',
                uploadedAt: '2 days ago'
            },
            {
                id: 2,
                title: 'Interior Design Specifications',
                description: 'Complete interior design requirements and material specifications',
                type: 'specification',
                size: '1.8 MB',
                uploadedAt: '1 week ago'
            },
            {
                id: 3,
                title: 'MEP Systems Design',
                description: 'Mechanical, electrical, and plumbing systems design',
                type: 'drawing',
                size: '3.2 MB',
                uploadedAt: '3 days ago'
            },
            {
                id: 4,
                title: '3D Renderings - Lobby',
                description: 'Photorealistic 3D renders of main lobby area',
                type: 'photo',
                size: '8.5 MB',
                uploadedAt: '1 day ago'
            },
            {
                id: 5,
                title: 'Lighting Design Calculations',
                description: 'Detailed lighting calculations and specifications',
                type: 'specification',
                size: '1.2 MB',
                uploadedAt: '4 days ago'
            },
            {
                id: 6,
                title: 'Landscape Design Plans',
                description: 'Rooftop garden design with plant selection',
                type: 'drawing',
                size: '2.1 MB',
                uploadedAt: '5 days ago'
            },
            {
                id: 7,
                title: 'Structural Analysis Report',
                description: 'Structural engineering analysis and calculations',
                type: 'report',
                size: '4.3 MB',
                uploadedAt: '1 week ago'
            },
            {
                id: 8,
                title: 'Client Presentation Deck',
                description: 'PowerPoint presentation for client review',
                type: 'other',
                size: '15.2 MB',
                uploadedAt: '2 days ago'
            }
        ],
        
        projectTimeline: [
            {
                id: 1,
                title: 'Project Kickoff',
                description: 'Initial project meeting and requirements gathering',
                date: 'Jan 15, 2024',
                status: 'completed'
            },
            {
                id: 2,
                title: 'Architectural Design Complete',
                description: 'All architectural plans finalized and approved',
                date: 'Feb 15, 2024',
                status: 'upcoming'
            },
            {
                id: 3,
                title: 'Interior Design Approval',
                description: 'Client approval for interior design concepts',
                date: 'Feb 28, 2024',
                status: 'upcoming'
            },
            {
                id: 4,
                title: 'Final Design Review',
                description: 'Complete design review and client presentation',
                date: 'Mar 15, 2024',
                status: 'upcoming'
            }
        ],
        
        getTaskStatusClass(status) {
            const classMap = {
                'todo': 'bg-gray-100 text-gray-800',
                'in_progress': 'bg-blue-100 text-blue-800',
                'completed': 'bg-green-100 text-green-800',
                'review': 'bg-yellow-100 text-yellow-800'
            };
            return classMap[status] || 'bg-gray-100 text-gray-800';
        },
        
        getTaskPriorityClass(priority) {
            const classMap = {
                'low': 'bg-gray-100 text-gray-800',
                'medium': 'bg-blue-100 text-blue-800',
                'high': 'bg-orange-100 text-orange-800',
                'urgent': 'bg-red-100 text-red-800'
            };
            return classMap[priority] || 'bg-gray-100 text-gray-800';
        },
        
        getDocumentIcon(type) {
            const iconMap = {
                'drawing': '📐',
                'specification': '📋',
                'contract': '📜',
                'report': '📊',
                'photo': '📷',
                'other': '📁'
            };
            return iconMap[type] || '📄';
        },
        
        getMilestoneStatusClass(status) {
            const classMap = {
                'completed': 'bg-green-100 text-green-800',
                'upcoming': 'bg-blue-100 text-blue-800',
                'pending': 'bg-yellow-100 text-yellow-800'
            };
            return classMap[status] || 'bg-gray-100 text-gray-800';
        },
        
        createTask() {
            window.location.href = '/tasks/create?project=1';
        },
        
        uploadDocument() {
            window.location.href = '/documents/create?project=1';
        },
        
        inviteTeamMember() {
            window.location.href = '/team/invite?project=1';
        },
        
        scheduleMeeting() {
            alert('Schedule meeting feature coming soon!');
        },
        
        generateReport() {
            alert('Generate report feature coming soon!');
        },
        
        addDesignPhase() {
            const newPhase = {
                id: this.designPhases.length + 1,
                name: 'New Design Phase',
                progress: 0,
                status: 'Planning',
                dueDate: 'TBD',
                colorClass: 'bg-gray-500',
                editing: false,
                originalName: 'New Design Phase'
            };
            this.designPhases.push(newPhase);
        },
        
        editPhase(phase) {
            // Store original name for cancel
            phase.originalName = phase.name;
            phase.editing = true;
            
            // Focus input after DOM update
            this.$nextTick(() => {
                const input = this.$el.querySelector(`input[data-phase-id="${phase.id}"]`);
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },
        
        savePhase(phase) {
            if (phase.name.trim() === '') {
                phase.name = phase.originalName;
            }
            phase.editing = false;
            phase.originalName = phase.name;
        },
        
        cancelEdit(phase) {
            phase.name = phase.originalName;
            phase.editing = false;
        },
        
        deletePhase(phase) {
            if (confirm(`Are you sure you want to delete "${phase.name}"?`)) {
                const index = this.designPhases.findIndex(p => p.id === phase.id);
                if (index > -1) {
                    this.designPhases.splice(index, 1);
                }
            }
        },
        
        updatePhaseProgress(phase) {
            // Update color based on progress
            if (phase.progress >= 80) {
                phase.colorClass = 'bg-green-500';
            } else if (phase.progress >= 60) {
                phase.colorClass = 'bg-blue-500';
            } else if (phase.progress >= 40) {
                phase.colorClass = 'bg-orange-500';
            } else {
                phase.colorClass = 'bg-gray-500';
            }
        },
        
        updatePhaseStatus(phase) {
            // Update color based on status
            switch(phase.status) {
                case 'Completed':
                    phase.colorClass = 'bg-green-500';
                    break;
                case 'In Progress':
                    phase.colorClass = 'bg-blue-500';
                    break;
                case 'Review':
                    phase.colorClass = 'bg-yellow-500';
                    break;
                case 'Planning':
                    phase.colorClass = 'bg-gray-500';
                    break;
            }
        },
        
        updatePhaseDueDate(phase) {
            // You can add validation or notifications here
            console.log(`Due date updated for ${phase.name}: ${phase.dueDate}`);
        }
    }
}
</script>
@endsection
