<?php $__env->startSection('title', 'Template Builder'); ?>
<?php $__env->startSection('page-title', 'Visual Template Builder'); ?>
<?php $__env->startSection('page-description', 'Create and edit project templates with visual drag-and-drop interface'); ?>
<?php $__env->startSection('user-initials', 'TB'); ?>
<?php $__env->startSection('user-name', 'Template Builder'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="templateBuilder()" class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Visual Template Builder</h1>
            <p class="text-gray-600 mt-1">Design your project template with drag-and-drop interface</p>
        </div>
        <div class="flex space-x-3">
            <button 
                @click="saveTemplate()"
                :disabled="!isTemplateValid"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
            >
                <i class="fas fa-save mr-2"></i>Save Template
            </button>
            <button 
                @click="previewTemplate()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                <i class="fas fa-eye mr-2"></i>Preview
            </button>
        </div>
    </div>

    <!-- Template Info -->
    <div class="dashboard-card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Template Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                <input 
                    type="text" 
                    x-model="template.name"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter template name"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select 
                    x-model="template.category"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">Select category</option>
                    <option value="residential">Residential</option>
                    <option value="commercial">Commercial</option>
                    <option value="industrial">Industrial</option>
                    <option value="mixed-use">Mixed-use</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Complexity Level</label>
                <select 
                    x-model="template.complexity_level"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="expert">Expert</option>
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea 
                x-model="template.description"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                rows="3"
                placeholder="Describe the template..."
            ></textarea>
        </div>
    </div>

    <!-- Visual Builder -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Task Library -->
        <div class="lg:col-span-1">
            <div class="dashboard-card p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📚 Task Library</h3>
                
                <!-- Search -->
                <div class="mb-4">
                    <input 
                        type="text" 
                        x-model="taskSearch"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Search tasks..."
                    >
                </div>

                <!-- Task Categories -->
                <div class="space-y-3">
                    <template x-for="category in taskCategories" :key="category.name">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2" x-text="category.name"></h4>
                            <div class="space-y-2">
                                <template x-for="task in getFilteredTasks(category.tasks)" :key="task.id">
                                    <div 
                                        draggable="true"
                                        @dragstart="startDrag($event, task)"
                                        class="p-2 bg-gray-50 border border-gray-200 rounded cursor-move hover:bg-gray-100 transition-colors"
                                    >
                                        <div class="text-sm font-medium text-gray-900" x-text="task.name"></div>
                                        <div class="text-xs text-gray-600" x-text="task.duration + ' days'"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div class="lg:col-span-3">
            <div class="dashboard-card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">🎨 Template Canvas</h3>
                    <div class="flex space-x-2">
                        <button 
                            @click="addPhase()"
                            class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors"
                        >
                            <i class="fas fa-plus mr-1"></i>Add Phase
                        </button>
                        <button 
                            @click="toggleView()"
                            class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700 transition-colors"
                        >
                            <i class="fas fa-th mr-1"></i><span x-text="viewMode === 'timeline' ? 'Timeline' : 'Grid'"></span>
                        </button>
                    </div>
                </div>

                <!-- Timeline View -->
                <div x-show="viewMode === 'timeline'" class="space-y-4">
                    <template x-for="(phase, phaseKey) in template.phases" :key="phaseKey">
                        <div 
                            class="border border-gray-200 rounded-lg p-4"
                            @drop="dropOnPhase($event, phaseKey)"
                            @dragover.prevent
                            @dragenter.prevent
                        >
                            <div class="flex justify-between items-center mb-3">
                                <div class="flex items-center space-x-3">
                                    <div 
                                        class="w-4 h-4 rounded-full"
                                        :class="getPhaseColorClass(phase.color)"
                                    ></div>
                                    
                                    <!-- Inline Editable Phase Name -->
                                    <div class="flex items-center space-x-2">
                                        <div x-show="!phase.editing">
                                            <h4 
                                                class="font-medium text-gray-900 cursor-pointer hover:text-blue-600 transition-colors px-2 py-1 rounded hover:bg-blue-50"
                                                @click="startEditPhase(phaseKey)"
                                                x-text="phase.name"
                                            ></h4>
                                        </div>
                                        <div x-show="phase.editing" class="flex items-center space-x-2">
                                            <input 
                                                type="text" 
                                                x-model="phase.name"
                                                class="font-medium text-gray-900 border border-blue-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                @keyup.enter="savePhaseName(phaseKey)"
                                                @keyup.escape="cancelEditPhase(phaseKey)"
                                                @blur="savePhaseName(phaseKey)"
                                                x-ref="phaseInput"
                                            >
                                            <button 
                                                @click="savePhaseName(phaseKey)"
                                                class="p-1 text-green-600 hover:text-green-800"
                                            >
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                            <button 
                                                @click="cancelEditPhase(phaseKey)"
                                                class="p-1 text-red-600 hover:text-red-800"
                                            >
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <span class="text-sm text-gray-500" x-text="getPhaseDuration(phaseKey) + ' days'"></span>
                                </div>
                                <div class="flex space-x-2">
                                    <button 
                                        @click="editPhase(phaseKey)"
                                        class="p-1 text-gray-400 hover:text-blue-600"
                                        x-show="!phase.editing"
                                    >
                                        <i class="fas fa-edit text-sm"></i>
                                    </button>
                                    <button 
                                        @click="deletePhase(phaseKey)"
                                        class="p-1 text-gray-400 hover:text-red-600"
                                        x-show="!phase.editing"
                                    >
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Phase Tasks -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <template x-for="task in getPhaseTasks(phaseKey)" :key="task.id">
                                    <div 
                                        class="p-3 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow cursor-move"
                                        draggable="true"
                                        @dragstart="startDrag($event, task)"
                                        @click="editTask(task)"
                                    >
                                        <div class="flex justify-between items-start mb-2">
                                            <!-- Inline Editable Task Name -->
                                            <div class="flex items-center space-x-2 flex-1">
                                                <div x-show="!task.editing">
                                                    <h5 
                                                        class="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600 transition-colors px-1 py-1 rounded hover:bg-blue-50"
                                                        @click="startEditTask(task)"
                                                        x-text="task.name"
                                                    ></h5>
                                                </div>
                                                <div x-show="task.editing" class="flex items-center space-x-1">
                                                    <input 
                                                        type="text" 
                                                        x-model="task.name"
                                                        class="text-sm font-medium text-gray-900 border border-blue-300 rounded px-1 py-1 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                        @keyup.enter="saveTaskName(task)"
                                                        @keyup.escape="cancelEditTask(task)"
                                                        @blur="saveTaskName(task)"
                                                        x-ref="taskInput"
                                                    >
                                                    <button 
                                                        @click="saveTaskName(task)"
                                                        class="p-1 text-green-600 hover:text-green-800"
                                                    >
                                                        <i class="fas fa-check text-xs"></i>
                                                    </button>
                                                    <button 
                                                        @click="cancelEditTask(task)"
                                                        class="p-1 text-red-600 hover:text-red-800"
                                                    >
                                                        <i class="fas fa-times text-xs"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <span 
                                                class="px-2 py-1 text-xs rounded-full"
                                                :class="getPriorityClass(task.priority)"
                                                x-text="task.priority"
                                            ></span>
                                        </div>
                                        <div class="text-xs text-gray-600 mb-2" x-text="task.duration_days + ' days'"></div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex space-x-1">
                                                <template x-for="dep in task.dependencies" :key="dep">
                                                    <div class="w-2 h-2 bg-red-500 rounded-full" title="Has dependencies"></div>
                                                </template>
                                            </div>
                                            <button 
                                                @click.stop="deleteTask(task)"
                                                class="p-1 text-gray-400 hover:text-red-600"
                                            >
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Grid View -->
                <div x-show="viewMode === 'grid'" class="space-y-6">
                    <template x-for="(phase, phaseKey) in template.phases" :key="phaseKey">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center space-x-3">
                                    <div 
                                        class="w-6 h-6 rounded-full flex items-center justify-center text-white text-sm font-bold"
                                        :class="getPhaseColorClass(phase.color)"
                                        x-text="phase.name.charAt(0)"
                                    ></div>
                                    
                                    <!-- Inline Editable Phase Name for Grid View -->
                                    <div class="flex items-center space-x-2">
                                        <div x-show="!phase.editing">
                                            <h4 
                                                class="font-medium text-gray-900 cursor-pointer hover:text-blue-600 transition-colors px-2 py-1 rounded hover:bg-blue-50"
                                                @click="startEditPhase(phaseKey)"
                                                x-text="phase.name"
                                            ></h4>
                                        </div>
                                        <div x-show="phase.editing" class="flex items-center space-x-2">
                                            <input 
                                                type="text" 
                                                x-model="phase.name"
                                                class="font-medium text-gray-900 border border-blue-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                @keyup.enter="savePhaseName(phaseKey)"
                                                @keyup.escape="cancelEditPhase(phaseKey)"
                                                @blur="savePhaseName(phaseKey)"
                                                x-ref="phaseInput"
                                            >
                                            <button 
                                                @click="savePhaseName(phaseKey)"
                                                class="p-1 text-green-600 hover:text-green-800"
                                            >
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                            <button 
                                                @click="cancelEditPhase(phaseKey)"
                                                class="p-1 text-red-600 hover:text-red-800"
                                            >
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <span class="text-sm text-gray-500" x-text="getPhaseDuration(phaseKey) + ' days'"></span>
                                </div>
                                <div class="flex space-x-2">
                                    <button 
                                        @click="editPhase(phaseKey)"
                                        class="p-1 text-gray-400 hover:text-blue-600"
                                        x-show="!phase.editing"
                                    >
                                        <i class="fas fa-edit text-sm"></i>
                                    </button>
                                    <button 
                                        @click="deletePhase(phaseKey)"
                                        class="p-1 text-gray-400 hover:text-red-600"
                                        x-show="!phase.editing"
                                    >
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Gantt-like Timeline -->
                            <div class="space-y-2">
                                <template x-for="task in getPhaseTasks(phaseKey)" :key="task.id">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-32 text-sm text-gray-700 truncate" x-text="task.name"></div>
                                        <div class="flex-1 bg-gray-200 rounded-full h-6 relative">
                                            <div 
                                                class="h-full rounded-full flex items-center justify-center text-white text-xs font-medium"
                                                :class="getPriorityClass(task.priority)"
                                                :style="`width: ${(task.duration_days / 30) * 100}%`"
                                            >
                                                <span x-text="task.duration_days + 'd'"></span>
                                            </div>
                                        </div>
                                        <div class="w-20 text-xs text-gray-500 text-right" x-text="task.priority"></div>
                                        <button 
                                            @click="editTask(task)"
                                            class="p-1 text-gray-400 hover:text-blue-600"
                                        >
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Editor Modal -->
    <div x-show="showTaskEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Edit Task</h2>
                <button @click="closeTaskEditor()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form @submit.prevent="saveTask()">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Task Name</label>
                        <input 
                            type="text" 
                            x-model="editingTask.name"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (Days)</label>
                        <input 
                            type="number" 
                            x-model="editingTask.duration_days"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            min="1"
                            required
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select 
                            x-model="editingTask.priority"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phase</label>
                        <select 
                            x-model="editingTask.phase_key"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <template x-for="(phase, phaseKey) in template.phases" :key="phaseKey">
                                <option :value="phaseKey" x-text="phase.name"></option>
                            </template>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea 
                        x-model="editingTask.description"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        rows="3"
                    ></textarea>
                </div>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dependencies</label>
                    <div class="space-y-2">
                        <template x-for="task in getAllTasks()" :key="task.id">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    :value="task.id"
                                    x-model="editingTask.dependencies"
                                    class="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="text-sm text-gray-700" x-text="task.name"></span>
                            </label>
                        </template>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button 
                        type="button"
                        @click="closeTaskEditor()"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Save Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Phase Editor Modal -->
    <div x-show="showPhaseEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Edit Phase</h2>
                <button @click="closePhaseEditor()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form @submit.prevent="savePhase()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phase Name</label>
                        <input 
                            type="text" 
                            x-model="editingPhase.name"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                        <select 
                            x-model="editingPhase.color"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="blue">Blue</option>
                            <option value="green">Green</option>
                            <option value="orange">Orange</option>
                            <option value="purple">Purple</option>
                            <option value="pink">Pink</option>
                            <option value="red">Red</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button 
                        type="button"
                        @click="closePhaseEditor()"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Save Phase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function templateBuilder() {
    return {
        viewMode: 'timeline',
        taskSearch: '',
        showTaskEditor: false,
        showPhaseEditor: false,
        editingTask: {},
        editingPhase: {},
        editingPhaseKey: '',
        draggedTask: null,
        
        template: {
            name: '',
            category: '',
            description: '',
            complexity_level: 'medium',
            phases: {
                architectural: {
                    name: 'Architectural Design',
                    color: 'blue',
                    editing: false,
                    originalName: 'Architectural Design'
                },
                structural: {
                    name: 'Structural Design',
                    color: 'green',
                    editing: false,
                    originalName: 'Structural Design'
                },
                mep: {
                    name: 'MEP Design',
                    color: 'orange',
                    editing: false,
                    originalName: 'MEP Design'
                }
            },
            tasks: []
        },
        
        taskCategories: [
            {
                name: 'Architectural',
                tasks: [
                    { id: 'arch-1', name: 'Site Analysis', duration: 5, priority: 'high', category: 'architectural' },
                    { id: 'arch-2', name: 'Concept Design', duration: 10, priority: 'high', category: 'architectural' },
                    { id: 'arch-3', name: 'Schematic Design', duration: 15, priority: 'high', category: 'architectural' },
                    { id: 'arch-4', name: 'Design Development', duration: 20, priority: 'medium', category: 'architectural' },
                    { id: 'arch-5', name: 'Construction Documents', duration: 25, priority: 'high', category: 'architectural' }
                ]
            },
            {
                name: 'Structural',
                tasks: [
                    { id: 'struct-1', name: 'Load Analysis', duration: 8, priority: 'high', category: 'structural' },
                    { id: 'struct-2', name: 'Foundation Design', duration: 12, priority: 'high', category: 'structural' },
                    { id: 'struct-3', name: 'Frame Design', duration: 15, priority: 'high', category: 'structural' },
                    { id: 'struct-4', name: 'Detail Drawings', duration: 10, priority: 'medium', category: 'structural' }
                ]
            },
            {
                name: 'MEP',
                tasks: [
                    { id: 'mep-1', name: 'Electrical Design', duration: 12, priority: 'high', category: 'mep' },
                    { id: 'mep-2', name: 'Mechanical Design', duration: 15, priority: 'high', category: 'mep' },
                    { id: 'mep-3', name: 'Plumbing Design', duration: 10, priority: 'medium', category: 'mep' },
                    { id: 'mep-4', name: 'Fire Protection', duration: 8, priority: 'high', category: 'mep' }
                ]
            }
        ],
        
        get isTemplateValid() {
            return this.template.name && this.template.category && Object.keys(this.template.phases).length > 0;
        },
        
        getFilteredTasks(tasks) {
            if (!this.taskSearch) return tasks;
            return tasks.filter(task => 
                task.name.toLowerCase().includes(this.taskSearch.toLowerCase())
            );
        },
        
        getPhaseTasks(phaseKey) {
            return this.template.tasks.filter(task => task.phase_key === phaseKey);
        },
        
        getPhaseDuration(phaseKey) {
            return this.getPhaseTasks(phaseKey).reduce((sum, task) => sum + task.duration_days, 0);
        },
        
        getAllTasks() {
            return this.template.tasks;
        },
        
        getPhaseColorClass(color) {
            const colorMap = {
                blue: 'bg-blue-500',
                green: 'bg-green-500',
                orange: 'bg-orange-500',
                purple: 'bg-purple-500',
                pink: 'bg-pink-500',
                red: 'bg-red-500'
            };
            return colorMap[color] || 'bg-gray-500';
        },
        
        getPriorityClass(priority) {
            const priorityMap = {
                low: 'bg-gray-100 text-gray-800',
                medium: 'bg-yellow-100 text-yellow-800',
                high: 'bg-orange-100 text-orange-800',
                critical: 'bg-red-100 text-red-800'
            };
            return priorityMap[priority] || 'bg-gray-100 text-gray-800';
        },
        
        startDrag(event, task) {
            this.draggedTask = task;
            event.dataTransfer.effectAllowed = 'move';
        },
        
        dropOnPhase(event, phaseKey) {
            event.preventDefault();
            if (this.draggedTask) {
                const task = { ...this.draggedTask };
                task.id = Date.now().toString();
                task.phase_key = phaseKey;
                task.dependencies = [];
                task.editing = false;
                task.originalName = task.name;
                this.template.tasks.push(task);
                this.draggedTask = null;
            }
        },
        
        addPhase() {
            const phaseKey = 'phase_' + Date.now();
            this.template.phases[phaseKey] = {
                name: 'New Phase',
                color: 'blue',
                editing: true,
                originalName: 'New Phase'
            };
            
            // Auto-focus on the new phase name input
            this.$nextTick(() => {
                const input = this.$refs.phaseInput;
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },
        
        editPhase(phaseKey) {
            this.editingPhase = { ...this.template.phases[phaseKey] };
            this.editingPhaseKey = phaseKey;
            this.showPhaseEditor = true;
        },
        
        savePhase() {
            this.template.phases[this.editingPhaseKey] = { ...this.editingPhase };
            this.closePhaseEditor();
        },
        
        deletePhase(phaseKey) {
            if (confirm('Are you sure you want to delete this phase?')) {
                delete this.template.phases[phaseKey];
                this.template.tasks = this.template.tasks.filter(task => task.phase_key !== phaseKey);
            }
        },
        
        editTask(task) {
            this.editingTask = { ...task };
            this.showTaskEditor = true;
        },
        
        saveTask() {
            const index = this.template.tasks.findIndex(t => t.id === this.editingTask.id);
            if (index > -1) {
                this.template.tasks[index] = { ...this.editingTask };
            }
            this.closeTaskEditor();
        },
        
        deleteTask(task) {
            if (confirm('Are you sure you want to delete this task?')) {
                this.template.tasks = this.template.tasks.filter(t => t.id !== task.id);
            }
        },
        
        toggleView() {
            this.viewMode = this.viewMode === 'timeline' ? 'grid' : 'timeline';
        },
        
        closeTaskEditor() {
            this.showTaskEditor = false;
            this.editingTask = {};
        },
        
        closePhaseEditor() {
            this.showPhaseEditor = false;
            this.editingPhase = {};
            this.editingPhaseKey = '';
        },
        
        saveTemplate() {
            if (!this.isTemplateValid) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Save template logic here
            alert('Template saved successfully!');
        },
        
        previewTemplate() {
            alert('Template preview feature coming soon!');
        },
        
        // Inline Phase Editing Functions
        startEditPhase(phaseKey) {
            const phase = this.template.phases[phaseKey];
            phase.editing = true;
            phase.originalName = phase.name;
            
            // Focus input after DOM update
            this.$nextTick(() => {
                const input = this.$refs.phaseInput;
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },
        
        savePhaseName(phaseKey) {
            const phase = this.template.phases[phaseKey];
            if (phase.name.trim() === '') {
                phase.name = phase.originalName;
            } else {
                // Smart validation: Check for duplicate phase names
                const duplicatePhases = Object.entries(this.template.phases)
                    .filter(([key, p]) => key !== phaseKey && p.name.toLowerCase() === phase.name.toLowerCase());
                
                if (duplicatePhases.length > 0) {
                    alert('Phase name already exists. Please choose a different name.');
                    return;
                }
            }
            phase.editing = false;
            phase.originalName = phase.name;
        },
        
        cancelEditPhase(phaseKey) {
            const phase = this.template.phases[phaseKey];
            phase.name = phase.originalName;
            phase.editing = false;
        },
        
        // Inline Task Editing Functions
        startEditTask(task) {
            task.editing = true;
            task.originalName = task.name;
            
            // Focus input after DOM update
            this.$nextTick(() => {
                const input = this.$refs.taskInput;
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },
        
        saveTaskName(task) {
            if (task.name.trim() === '') {
                task.name = task.originalName;
            } else {
                // Smart validation: Check for duplicate task names in the same phase
                const duplicateTasks = this.template.tasks
                    .filter(t => t.id !== task.id && 
                                t.phase_key === task.phase_key && 
                                t.name.toLowerCase() === task.name.toLowerCase());
                
                if (duplicateTasks.length > 0) {
                    alert('Task name already exists in this phase. Please choose a different name.');
                    return;
                }
            }
            task.editing = false;
            task.originalName = task.name;
        },
        
        cancelEditTask(task) {
            task.name = task.originalName;
            task.editing = false;
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/templates/builder.blade.php ENDPATH**/ ?>