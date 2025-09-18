<?php $__env->startSection('title', 'Construction Project - Shopping Mall Development'); ?>
<?php $__env->startSection('page-title', 'Shopping Mall Development'); ?>
<?php $__env->startSection('page-description', 'Large shopping mall construction with retail spaces and entertainment areas'); ?>
<?php $__env->startSection('user-initials', 'PM'); ?>
<?php $__env->startSection('user-name', 'Project Manager'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="constructionProject()">
    <!-- Overview Tab -->
    <div x-show="activeTab === 'overview'">
        <!-- Project Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="dashboard-card metric-card orange p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Construction Progress</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.constructionProgress + '%'"></p>
                        <p class="text-white/80 text-sm">+8% this week</p>
                    </div>
                    <i class="fas fa-hard-hat text-4xl text-white/60"></i>
                </div>
            </div>

            <div class="dashboard-card metric-card green p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Active Tasks</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.activeTasks"></p>
                        <p class="text-white/80 text-sm">15 in progress</p>
                    </div>
                    <i class="fas fa-tasks text-4xl text-white/60"></i>
                </div>
            </div>

            <div class="dashboard-card metric-card blue p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Workers On Site</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.workersOnSite"></p>
                        <p class="text-white/80 text-sm">Construction team</p>
                    </div>
                    <i class="fas fa-users text-4xl text-white/60"></i>
                </div>
            </div>

            <div class="dashboard-card metric-card red p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/80 text-sm">Safety Score</p>
                        <p class="text-3xl font-bold text-white" x-text="projectStats.safetyScore + '%'"></p>
                        <p class="text-white/80 text-sm">Excellent</p>
                    </div>
                    <i class="fas fa-shield-alt text-4xl text-white/60"></i>
                </div>
            </div>
        </div>

        <!-- Project Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Project Info -->
            <div class="lg:col-span-2">
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">🏗️ Project Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Project Type</label>
                            <p class="text-gray-900">Construction Project</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Start Date</label>
                            <p class="text-gray-900">Dec 1, 2023</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Due Date</label>
                            <p class="text-gray-900">Feb 28, 2024</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Client</label>
                            <p class="text-gray-900">XYZ Group</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Budget</label>
                            <p class="text-gray-900">$8,500,000</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="text-sm font-medium text-gray-500 mb-2 block">Description</label>
                        <p class="text-gray-700">Large shopping mall development with retail spaces, entertainment areas, food court, and parking facilities. The project includes structural construction, MEP installation, and interior finishing works.</p>
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
                            @click="reportIncident()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-exclamation-triangle mr-2"></i>Report Incident
                        </button>
                        <button 
                            @click="scheduleInspection()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-search mr-2"></i>Schedule Inspection
                        </button>
                        <button 
                            @click="updateProgress()"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
                        >
                            <i class="fas fa-chart-line mr-2"></i>Update Progress
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Construction Progress -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="dashboard-card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">🏗️ Construction Progress</h3>
                    <button 
                        @click="addConstructionPhase()"
                        class="px-3 py-1 bg-orange-600 text-white rounded text-sm hover:bg-orange-700 transition-colors"
                    >
                        <i class="fas fa-plus mr-1"></i>Add Phase
                    </button>
                </div>
                <div class="space-y-4">
                    <template x-for="constructionPhase in constructionPhases" :key="constructionPhase.id">
                        <div class="border border-gray-200 rounded-lg p-3 hover:border-gray-300 transition-colors">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex-1">
                                    <!-- Editable Phase Name -->
                                    <div x-show="!constructionPhase.editing">
                                        <span 
                                            class="text-sm font-medium text-gray-900 cursor-pointer hover:text-orange-600"
                                            @click="editPhase(constructionPhase)"
                                            x-text="constructionPhase.name"
                                        ></span>
                                    </div>
                                    <div x-show="constructionPhase.editing" class="flex items-center space-x-2">
                                        <input 
                                            type="text" 
                                            x-model="constructionPhase.name"
                                            :data-phase-id="constructionPhase.id"
                                            class="text-sm font-medium text-gray-900 border border-orange-300 rounded px-2 py-1 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                            @keyup.enter="savePhase(constructionPhase)"
                                            @keyup.escape="cancelEdit(constructionPhase)"
                                        >
                                        <button 
                                            @click="savePhase(constructionPhase)"
                                            class="text-green-600 hover:text-green-800"
                                        >
                                            <i class="fas fa-check text-xs"></i>
                                        </button>
                                        <button 
                                            @click="cancelEdit(constructionPhase)"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600" x-text="constructionPhase.progress + '%'"></span>
                                    <div class="flex space-x-1">
                                        <button 
                                            @click="editPhase(constructionPhase)"
                                            class="p-1 text-gray-400 hover:text-orange-600"
                                            x-show="!constructionPhase.editing"
                                        >
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button 
                                            @click="deletePhase(constructionPhase)"
                                            class="p-1 text-gray-400 hover:text-red-600"
                                            x-show="!constructionPhase.editing"
                                        >
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                <div 
                                    class="h-2 rounded-full transition-all duration-500"
                                    :class="constructionPhase.colorClass"
                                    :style="`width: ${constructionPhase.progress}%`"
                                ></div>
                            </div>
                            <div class="flex justify-between items-center text-xs text-gray-500">
                                <div class="flex items-center space-x-2">
                                    <span x-text="constructionPhase.status"></span>
                                    <select 
                                        x-model="constructionPhase.status"
                                        class="text-xs border border-gray-300 rounded px-1 py-0.5 focus:ring-1 focus:ring-orange-500 focus:border-orange-500"
                                        @change="updatePhaseStatus(constructionPhase)"
                                    >
                                        <option value="Planning">Planning</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Review">Review</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span x-text="constructionPhase.dueDate"></span>
                                    <input 
                                        type="date" 
                                        x-model="constructionPhase.dueDate"
                                        class="text-xs border border-gray-300 rounded px-1 py-0.5 focus:ring-1 focus:ring-orange-500 focus:border-orange-500"
                                        @change="updatePhaseDueDate(constructionPhase)"
                                    >
                                </div>
                            </div>
                            <div class="mt-2 flex items-center space-x-2">
                                <span class="text-xs text-gray-500">Progress:</span>
                                <input 
                                    type="range" 
                                    min="0" 
                                    max="100" 
                                    x-model="constructionPhase.progress"
                                    class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                    @input="updatePhaseProgress(constructionPhase)"
                                >
                                <span class="text-xs font-medium text-gray-700" x-text="constructionPhase.progress + '%'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="dashboard-card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📅 Upcoming Milestones</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900">MEP Installation Complete</p>
                            <p class="text-sm text-gray-600">Feb 20, 2024</p>
                        </div>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">On Track</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900">Interior Finishing Phase</p>
                            <p class="text-sm text-gray-600">Feb 25, 2024</p>
                        </div>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Upcoming</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900">Final Inspection</p>
                            <p class="text-sm text-gray-600">Feb 28, 2024</p>
                        </div>
                        <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">Pending</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Safety & Quality -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="dashboard-card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🛡️ Safety Status</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Days Without Incident</span>
                        <span class="text-lg font-semibold text-green-600">45 days</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Safety Training Hours</span>
                        <span class="text-lg font-semibold text-blue-600">320 hours</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Safety Inspections</span>
                        <span class="text-lg font-semibold text-purple-600">12 completed</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Safety Score</span>
                        <span class="text-lg font-semibold text-green-600">98%</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">✅ Quality Control</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Quality Inspections</span>
                        <span class="text-lg font-semibold text-blue-600">28 completed</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Defects Found</span>
                        <span class="text-lg font-semibold text-orange-600">3 minor</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Defects Fixed</span>
                        <span class="text-lg font-semibold text-green-600">3/3</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Quality Score</span>
                        <span class="text-lg font-semibold text-green-600">96%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Construction Gallery -->
        <div class="dashboard-card p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📸 Construction Gallery</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="aspect-square bg-gradient-to-br from-orange-100 to-orange-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">🏗️</div>
                        <div class="text-sm font-medium text-orange-800">Foundation Work</div>
                    </div>
                </div>
                <div class="aspect-square bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">⚡</div>
                        <div class="text-sm font-medium text-blue-800">MEP Installation</div>
                    </div>
                </div>
                <div class="aspect-square bg-gradient-to-br from-green-100 to-green-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">🏪</div>
                        <div class="text-sm font-medium text-green-800">Retail Spaces</div>
                    </div>
                </div>
                <div class="aspect-square bg-gradient-to-br from-purple-100 to-purple-200 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-4xl mb-2">🚧</div>
                        <div class="text-sm font-medium text-purple-800">Steel Structure</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📈 Recent Activities</h3>
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check text-green-600 text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Foundation work completed for Block A</p>
                        <p class="text-sm text-gray-600">Construction team finished foundation work</p>
                        <p class="text-xs text-gray-500">1 hour ago</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-tools text-blue-600 text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">MEP installation progress update</p>
                        <p class="text-sm text-gray-600">Electrical systems 70% complete</p>
                        <p class="text-xs text-gray-500">3 hours ago</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-shield-alt text-orange-600 text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety inspection completed</p>
                        <p class="text-sm text-gray-600">Weekly safety check passed with 98% score</p>
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
                <h3 class="text-lg font-semibold text-gray-900">📝 Construction Tasks</h3>
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
                <h3 class="text-lg font-semibold text-gray-900">👥 Construction Team</h3>
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
                        <div class="w-16 h-16 bg-orange-600 rounded-full flex items-center justify-center text-white font-semibold text-xl mx-auto mb-3">
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
                <h3 class="text-lg font-semibold text-gray-900">📄 Construction Documents</h3>
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
                <h3 class="text-lg font-semibold text-gray-900">📊 Construction Gantt Chart</h3>
                <div class="flex space-x-2">
                    <button 
                        @click="ganttView = 'month'"
                        :class="ganttView === 'month' ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-3 py-1 rounded text-sm transition-colors"
                    >
                        Month View
                    </button>
                    <button 
                        @click="ganttView = 'week'"
                        :class="ganttView === 'week' ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700'"
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
                        <div class="w-48 px-4 py-2 font-medium text-gray-900">Construction Phase</div>
                        <div class="flex-1 px-4 py-2 font-medium text-gray-900 text-center">Timeline</div>
                        <div class="w-20 px-4 py-2 font-medium text-gray-900 text-center">Progress</div>
                    </div>
                    
                    <!-- Gantt Rows -->
                    <template x-for="phase in constructionPhases" :key="phase.id">
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
            <h3 class="text-lg font-semibold text-gray-900 mb-6">📅 Construction Milestones</h3>
            <div class="space-y-6">
                <template x-for="milestone in projectTimeline" :key="milestone.id">
                    <div class="flex items-start space-x-4">
                        <div class="w-4 h-4 bg-orange-600 rounded-full mt-2"></div>
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
                            <input type="text" value="Shopping Mall Development" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Description</label>
                            <textarea rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">Large shopping mall construction project</textarea>
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
                    <h4 class="font-medium text-gray-900 mb-4">Safety & Quality Settings</h4>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" checked class="mr-2">
                            <span class="text-sm">Daily safety checks</span>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" checked class="mr-2">
                            <span class="text-sm">Quality inspections</span>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" class="mr-2">
                            <span class="text-sm">Weather alerts</span>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" checked class="mr-2">
                            <span class="text-sm">Progress reports</span>
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
function constructionProject() {
    return {
        ganttView: 'month',
        
        projectStats: {
            constructionProgress: 72,
            activeTasks: 18,
            workersOnSite: 65,
            safetyScore: 96
        },
        
        constructionPhases: [
            {
                id: 1,
                name: 'Foundation & Structure',
                progress: 98,
                status: 'Completed',
                dueDate: '2024-01-30',
                colorClass: 'bg-green-500',
                editing: false,
                originalName: 'Foundation & Structure'
            },
            {
                id: 2,
                name: 'MEP Installation',
                progress: 78,
                status: 'In Progress',
                dueDate: '2024-02-20',
                colorClass: 'bg-blue-500',
                editing: false,
                originalName: 'MEP Installation'
            },
            {
                id: 3,
                name: 'Interior Finishing',
                progress: 52,
                status: 'In Progress',
                dueDate: '2024-02-25',
                colorClass: 'bg-orange-500',
                editing: false,
                originalName: 'Interior Finishing'
            },
            {
                id: 4,
                name: 'Exterior & Landscaping',
                progress: 35,
                status: 'Planning',
                dueDate: '2024-03-15',
                colorClass: 'bg-purple-500',
                editing: false,
                originalName: 'Exterior & Landscaping'
            },
            {
                id: 5,
                name: 'Quality Control',
                progress: 85,
                status: 'In Progress',
                dueDate: '2024-02-28',
                colorClass: 'bg-teal-500',
                editing: false,
                originalName: 'Quality Control'
            },
            {
                id: 6,
                name: 'Safety Compliance',
                progress: 95,
                status: 'Completed',
                dueDate: '2024-02-15',
                colorClass: 'bg-red-500',
                editing: false,
                originalName: 'Safety Compliance'
            }
        ],
        
        projectTasks: [
            {
                id: 1,
                title: 'Foundation Work - Block A',
                description: 'Complete foundation work including excavation, concrete pouring, and curing',
                status: 'completed',
                priority: 'high',
                assignee: 'John Smith',
                dueDate: 'Jan 30, 2024',
                estimatedHours: 48
            },
            {
                id: 2,
                title: 'MEP Installation - Floor 2',
                description: 'Install electrical, plumbing, and HVAC systems for retail floor',
                status: 'in_progress',
                priority: 'high',
                assignee: 'Mike Johnson',
                dueDate: 'Feb 20, 2024',
                estimatedHours: 40
            },
            {
                id: 3,
                title: 'Interior Finishing - Retail Spaces',
                description: 'Complete interior finishing including flooring, ceiling, and fixtures',
                status: 'in_progress',
                priority: 'high',
                assignee: 'Sarah Wilson',
                dueDate: 'Feb 25, 2024',
                estimatedHours: 56
            },
            {
                id: 4,
                title: 'Structural Steel - Block B',
                description: 'Install structural steel framework for Block B',
                status: 'in_progress',
                priority: 'high',
                assignee: 'Carlos Rodriguez',
                dueDate: 'Feb 28, 2024',
                estimatedHours: 64
            },
            {
                id: 5,
                title: 'Exterior Cladding - East Facade',
                description: 'Install glass curtain wall and exterior cladding',
                status: 'todo',
                priority: 'medium',
                assignee: 'David Kim',
                dueDate: 'Mar 5, 2024',
                estimatedHours: 32
            },
            {
                id: 6,
                title: 'Parking Structure - Level 1',
                description: 'Complete parking structure construction and marking',
                status: 'todo',
                priority: 'medium',
                assignee: 'Maria Garcia',
                dueDate: 'Mar 10, 2024',
                estimatedHours: 24
            },
            {
                id: 7,
                title: 'Landscaping - Main Entrance',
                description: 'Install landscaping and irrigation systems',
                status: 'review',
                priority: 'low',
                assignee: 'Tom Wilson',
                dueDate: 'Mar 15, 2024',
                estimatedHours: 16
            },
            {
                id: 8,
                title: 'Safety Inspection - Weekly',
                description: 'Conduct weekly safety inspection and compliance check',
                status: 'completed',
                priority: 'urgent',
                assignee: 'Safety Team',
                dueDate: 'Feb 12, 2024',
                estimatedHours: 8
            }
        ],
        
        projectTeam: [
            {
                id: 1,
                name: 'John Smith',
                role: 'Site Engineer',
                department: 'Construction',
                initials: 'JS'
            },
            {
                id: 2,
                name: 'Mike Johnson',
                role: 'MEP Supervisor',
                department: 'Engineering',
                initials: 'MJ'
            },
            {
                id: 3,
                name: 'Sarah Wilson',
                role: 'Quality Inspector',
                department: 'Quality',
                initials: 'SW'
            },
            {
                id: 4,
                name: 'Carlos Rodriguez',
                role: 'Structural Supervisor',
                department: 'Construction',
                initials: 'CR'
            },
            {
                id: 5,
                name: 'David Kim',
                role: 'Facade Specialist',
                department: 'Construction',
                initials: 'DK'
            },
            {
                id: 6,
                name: 'Maria Garcia',
                role: 'Concrete Specialist',
                department: 'Construction',
                initials: 'MG'
            },
            {
                id: 7,
                name: 'Tom Wilson',
                role: 'Landscape Contractor',
                department: 'Landscaping',
                initials: 'TW'
            },
            {
                id: 8,
                name: 'Safety Team',
                role: 'Safety Officer',
                department: 'Safety',
                initials: 'ST'
            }
        ],
        
        projectDocuments: [
            {
                id: 1,
                title: 'Construction Plans - Block A',
                description: 'Detailed construction drawings and specifications',
                type: 'drawing',
                size: '3.2 MB',
                uploadedAt: '1 day ago'
            },
            {
                id: 2,
                title: 'Safety Inspection Report',
                description: 'Weekly safety inspection results and compliance check',
                type: 'report',
                size: '1.5 MB',
                uploadedAt: '2 days ago'
            },
            {
                id: 3,
                title: 'MEP Installation Guide',
                description: 'MEP installation procedures and specifications',
                type: 'specification',
                size: '2.8 MB',
                uploadedAt: '3 days ago'
            },
            {
                id: 4,
                title: 'Progress Photos - Week 8',
                description: 'Construction progress photos from week 8',
                type: 'photo',
                size: '12.3 MB',
                uploadedAt: '1 day ago'
            },
            {
                id: 5,
                title: 'Concrete Test Results',
                description: 'Concrete strength test results and quality reports',
                type: 'report',
                size: '2.1 MB',
                uploadedAt: '4 days ago'
            },
            {
                id: 6,
                title: 'Steel Delivery Receipts',
                description: 'Structural steel delivery receipts and certificates',
                type: 'contract',
                size: '1.8 MB',
                uploadedAt: '5 days ago'
            },
            {
                id: 7,
                title: 'Daily Work Logs',
                description: 'Daily construction work logs and progress reports',
                type: 'report',
                size: '3.5 MB',
                uploadedAt: '1 day ago'
            },
            {
                id: 8,
                title: 'Equipment Maintenance Records',
                description: 'Heavy equipment maintenance and inspection records',
                type: 'report',
                size: '4.2 MB',
                uploadedAt: '3 days ago'
            }
        ],
        
        projectTimeline: [
            {
                id: 1,
                title: 'Project Groundbreaking',
                description: 'Official project start and site preparation',
                date: 'Dec 1, 2023',
                status: 'completed'
            },
            {
                id: 2,
                title: 'Foundation Complete',
                description: 'All foundation work completed',
                date: 'Jan 30, 2024',
                status: 'completed'
            },
            {
                id: 3,
                title: 'MEP Installation Complete',
                description: 'All MEP systems installed and tested',
                date: 'Feb 20, 2024',
                status: 'upcoming'
            },
            {
                id: 4,
                title: 'Project Handover',
                description: 'Final inspection and project handover',
                date: 'Feb 28, 2024',
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
            window.location.href = '/tasks/create?project=2';
        },
        
        uploadDocument() {
            window.location.href = '/documents/create?project=2';
        },
        
        inviteTeamMember() {
            window.location.href = '/team/invite?project=2';
        },
        
        reportIncident() {
            alert('Report incident feature coming soon!');
        },
        
        scheduleInspection() {
            alert('Schedule inspection feature coming soon!');
        },
        
        updateProgress() {
            alert('Update progress feature coming soon!');
        },
        
        addConstructionPhase() {
            const newPhase = {
                id: this.constructionPhases.length + 1,
                name: 'New Construction Phase',
                progress: 0,
                status: 'Planning',
                dueDate: 'TBD',
                colorClass: 'bg-gray-500',
                editing: false,
                originalName: 'New Construction Phase'
            };
            this.constructionPhases.push(newPhase);
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
                const index = this.constructionPhases.findIndex(p => p.id === phase.id);
                if (index > -1) {
                    this.constructionPhases.splice(index, 1);
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.project-detail', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/projects/construction-project.blade.php ENDPATH**/ ?>