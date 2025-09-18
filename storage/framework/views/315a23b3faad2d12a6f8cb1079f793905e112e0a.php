<?php $__env->startSection('title', 'Construction Template Builder'); ?>
<?php $__env->startSection('page-title', 'Construction Template Builder'); ?>
<?php $__env->startSection('page-description', 'Create and edit construction project templates with specialized phases'); ?>
<?php $__env->startSection('user-initials', 'CB'); ?>
<?php $__env->startSection('user-name', 'Construction Builder'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="constructionTemplateBuilder()" class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🏗️ Construction Template Builder</h1>
            <p class="text-gray-600 mt-1">Design construction project templates with specialized phases and workflows</p>
        </div>
        <div class="flex space-x-3">
            <button 
                @click="saveTemplate()"
                :disabled="!isTemplateValid"
                class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
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
        <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Construction Template Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                <input 
                    type="text" 
                    x-model="template.name"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    placeholder="Enter template name"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Construction Type</label>
                <select 
                    x-model="template.construction_type"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                >
                    <option value="">Select type</option>
                    <option value="residential">Residential</option>
                    <option value="commercial">Commercial</option>
                    <option value="industrial">Industrial</option>
                    <option value="infrastructure">Infrastructure</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Complexity Level</label>
                <select 
                    x-model="template.complexity_level"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
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
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                rows="3"
                placeholder="Describe the construction template..."
            ></textarea>
        </div>
    </div>

    <!-- Visual Builder -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Construction Task Library -->
        <div class="lg:col-span-1">
            <div class="dashboard-card p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🔨 Construction Task Library</h3>
                
                <!-- Search -->
                <div class="mb-4">
                    <input 
                        type="text" 
                        x-model="taskSearch"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                        placeholder="Search construction tasks..."
                    >
                </div>

                <!-- Construction Task Categories -->
                <div class="space-y-3">
                    <template x-for="category in constructionTaskCategories" :key="category.name">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2" x-text="category.name"></h4>
                            <div class="space-y-2">
                                <template x-for="task in getFilteredTasks(category.tasks)" :key="task.id">
                                    <div 
                                        draggable="true"
                                        @dragstart="startDrag($event, task)"
                                        class="p-2 bg-orange-50 border border-orange-200 rounded cursor-move hover:bg-orange-100 transition-colors"
                                    >
                                        <div class="text-sm font-medium text-gray-900" x-text="task.name"></div>
                                        <div class="text-xs text-gray-600" x-text="task.duration + ' days'"></div>
                                        <div class="text-xs text-orange-600" x-text="task.priority"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Construction Canvas -->
        <div class="lg:col-span-3">
            <div class="dashboard-card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">🏗️ Construction Template Canvas</h3>
                    <div class="flex space-x-2">
                        <button 
                            @click="addConstructionPhase()"
                            class="px-3 py-1 bg-orange-600 text-white rounded text-sm hover:bg-orange-700 transition-colors"
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
                                        :class="getConstructionPhaseColorClass(phase.color)"
                                    ></div>
                                    
                                    <!-- Inline Editable Phase Name -->
                                    <div class="flex items-center space-x-2">
                                        <div x-show="!phase.editing">
                                            <h4 
                                                class="font-medium text-gray-900 cursor-pointer hover:text-orange-600 transition-colors px-2 py-1 rounded hover:bg-orange-50"
                                                @click="startEditPhase(phaseKey)"
                                                x-text="phase.name"
                                            ></h4>
                                        </div>
                                        <div x-show="phase.editing" class="flex items-center space-x-2">
                                            <input 
                                                type="text" 
                                                x-model="phase.name"
                                                class="font-medium text-gray-900 border border-orange-300 rounded px-2 py-1 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
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
                                        class="p-1 text-gray-400 hover:text-orange-600"
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
                                                        class="text-sm font-medium text-gray-900 cursor-pointer hover:text-orange-600 transition-colors px-1 py-1 rounded hover:bg-orange-50"
                                                        @click="startEditTask(task)"
                                                        x-text="task.name"
                                                    ></h5>
                                                </div>
                                                <div x-show="task.editing" class="flex items-center space-x-1">
                                                    <input 
                                                        type="text" 
                                                        x-model="task.name"
                                                        class="text-sm font-medium text-gray-900 border border-orange-300 rounded px-1 py-1 focus:ring-1 focus:ring-orange-500 focus:border-orange-500"
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
                                                @click="deleteTask(task)"
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
                                        :class="getConstructionPhaseColorClass(phase.color)"
                                        x-text="phase.name.charAt(0)"
                                    ></div>
                                    
                                    <!-- Inline Editable Phase Name for Grid View -->
                                    <div class="flex items-center space-x-2">
                                        <div x-show="!phase.editing">
                                            <h4 
                                                class="font-medium text-gray-900 cursor-pointer hover:text-orange-600 transition-colors px-2 py-1 rounded hover:bg-orange-50"
                                                @click="startEditPhase(phaseKey)"
                                                x-text="phase.name"
                                            ></h4>
                                        </div>
                                        <div x-show="phase.editing" class="flex items-center space-x-2">
                                            <input 
                                                type="text" 
                                                x-model="phase.name"
                                                class="font-medium text-gray-900 border border-orange-300 rounded px-2 py-1 focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
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
                                        class="p-1 text-gray-400 hover:text-orange-600"
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
                                            class="p-1 text-gray-400 hover:text-orange-600"
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
</div>

<script>
function constructionTemplateBuilder() {
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
            construction_type: '',
            description: '',
            complexity_level: 'medium',
            phases: {
                pre_construction: {
                    name: 'Pre-Construction',
                    color: 'gray',
                    editing: false,
                    originalName: 'Pre-Construction'
                },
                foundation: {
                    name: 'Foundation & Structure',
                    color: 'brown',
                    editing: false,
                    originalName: 'Foundation & Structure'
                },
                mep_installation: {
                    name: 'MEP Installation',
                    color: 'blue',
                    editing: false,
                    originalName: 'MEP Installation'
                },
                interior: {
                    name: 'Interior Finishing',
                    color: 'orange',
                    editing: false,
                    originalName: 'Interior Finishing'
                },
                exterior: {
                    name: 'Exterior & Landscaping',
                    color: 'green',
                    editing: false,
                    originalName: 'Exterior & Landscaping'
                },
                final_inspection: {
                    name: 'Final Inspection & Handover',
                    color: 'purple',
                    editing: false,
                    originalName: 'Final Inspection & Handover'
                }
            },
            tasks: []
        },
        
        constructionTaskCategories: [
            {
                name: 'Pre-Construction',
                tasks: [
                    { id: 'pre-1', name: 'Site Preparation', duration: 5, priority: 'high', category: 'pre_construction' },
                    { id: 'pre-2', name: 'Permit Acquisition', duration: 10, priority: 'critical', category: 'pre_construction' },
                    { id: 'pre-3', name: 'Material Procurement', duration: 8, priority: 'high', category: 'pre_construction' },
                    { id: 'pre-4', name: 'Equipment Setup', duration: 3, priority: 'medium', category: 'pre_construction' },
                    { id: 'pre-5', name: 'Safety Planning', duration: 2, priority: 'critical', category: 'pre_construction' }
                ]
            },
            {
                name: 'Foundation & Structure',
                tasks: [
                    { id: 'found-1', name: 'Excavation', duration: 7, priority: 'high', category: 'foundation' },
                    { id: 'found-2', name: 'Foundation Pouring', duration: 5, priority: 'critical', category: 'foundation' },
                    { id: 'found-3', name: 'Foundation Curing', duration: 14, priority: 'high', category: 'foundation' },
                    { id: 'found-4', name: 'Structural Frame', duration: 21, priority: 'critical', category: 'foundation' },
                    { id: 'found-5', name: 'Roof Structure', duration: 10, priority: 'high', category: 'foundation' }
                ]
            },
            {
                name: 'MEP Installation',
                tasks: [
                    { id: 'mep-1', name: 'Electrical Rough-in', duration: 12, priority: 'high', category: 'mep_installation' },
                    { id: 'mep-2', name: 'Plumbing Rough-in', duration: 10, priority: 'high', category: 'mep_installation' },
                    { id: 'mep-3', name: 'HVAC Installation', duration: 15, priority: 'high', category: 'mep_installation' },
                    { id: 'mep-4', name: 'Fire Protection System', duration: 8, priority: 'critical', category: 'mep_installation' },
                    { id: 'mep-5', name: 'MEP Testing', duration: 5, priority: 'high', category: 'mep_installation' }
                ]
            },
            {
                name: 'Interior Finishing',
                tasks: [
                    { id: 'int-1', name: 'Drywall Installation', duration: 8, priority: 'medium', category: 'interior' },
                    { id: 'int-2', name: 'Flooring Installation', duration: 12, priority: 'medium', category: 'interior' },
                    { id: 'int-3', name: 'Paint & Finishes', duration: 6, priority: 'medium', category: 'interior' },
                    { id: 'int-4', name: 'Kitchen Installation', duration: 10, priority: 'high', category: 'interior' },
                    { id: 'int-5', name: 'Bathroom Installation', duration: 8, priority: 'high', category: 'interior' }
                ]
            },
            {
                name: 'Exterior & Landscaping',
                tasks: [
                    { id: 'ext-1', name: 'Exterior Siding', duration: 10, priority: 'medium', category: 'exterior' },
                    { id: 'ext-2', name: 'Roofing Installation', duration: 7, priority: 'high', category: 'exterior' },
                    { id: 'ext-3', name: 'Windows & Doors', duration: 5, priority: 'medium', category: 'exterior' },
                    { id: 'ext-4', name: 'Landscaping', duration: 8, priority: 'low', category: 'exterior' },
                    { id: 'ext-5', name: 'Driveway & Walkways', duration: 6, priority: 'medium', category: 'exterior' }
                ]
            },
            {
                name: 'Final Inspection',
                tasks: [
                    { id: 'final-1', name: 'Final Inspection', duration: 3, priority: 'critical', category: 'final_inspection' },
                    { id: 'final-2', name: 'Punch List Completion', duration: 5, priority: 'high', category: 'final_inspection' },
                    { id: 'final-3', name: 'Cleaning & Preparation', duration: 3, priority: 'medium', category: 'final_inspection' },
                    { id: 'final-4', name: 'Documentation', duration: 2, priority: 'medium', category: 'final_inspection' },
                    { id: 'final-5', name: 'Client Handover', duration: 1, priority: 'critical', category: 'final_inspection' }
                ]
            }
        ],
        
        get isTemplateValid() {
            return this.template.name && this.template.construction_type && Object.keys(this.template.phases).length > 0;
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
        
        getConstructionPhaseColorClass(color) {
            const colorMap = {
                gray: 'bg-gray-500',
                brown: 'bg-amber-700',
                blue: 'bg-blue-500',
                orange: 'bg-orange-500',
                green: 'bg-green-500',
                purple: 'bg-purple-500'
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
        
        addConstructionPhase() {
            const phaseKey = 'phase_' + Date.now();
            this.template.phases[phaseKey] = {
                name: 'New Construction Phase',
                color: 'gray',
                editing: true,
                originalName: 'New Construction Phase'
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
            alert('Construction template saved successfully!');
        },
        
        previewTemplate() {
            alert('Construction template preview feature coming soon!');
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

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/templates/construction-builder.blade.php ENDPATH**/ ?>