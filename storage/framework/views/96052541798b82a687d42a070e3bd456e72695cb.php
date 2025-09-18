<?php $__env->startSection('title', 'Project Templates'); ?>
<?php $__env->startSection('page-title', 'Project Templates'); ?>
<?php $__env->startSection('page-description', 'Manage and apply project templates for efficient project creation'); ?>
<?php $__env->startSection('user-initials', 'PT'); ?>
<?php $__env->startSection('user-name', 'Template Manager'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="templateManager()" class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Project Templates</h1>
            <p class="text-gray-600 mt-1">Create, manage, and apply project templates</p>
        </div>
        <div class="flex space-x-3">
            <button 
                @click="goToBuilder()"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
            >
                <i class="fas fa-magic mr-2"></i>Design Builder
            </button>
            <button 
                @click="goToConstructionBuilder()"
                class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors"
            >
                <i class="fas fa-hammer mr-2"></i>Construction Builder
            </button>
            <button 
                @click="goToAnalytics()"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
                <i class="fas fa-chart-bar mr-2"></i>Analytics
            </button>
            <button 
                @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                <i class="fas fa-plus mr-2"></i>Create Template
            </button>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex space-x-1 bg-gray-100 p-1 rounded-lg">
        <button 
            @click="selectedCategory = 'all'"
            :class="selectedCategory === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
        >
            All Templates
        </button>
        <button 
            @click="selectedCategory = 'residential'"
            :class="selectedCategory === 'residential' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
        >
            Residential
        </button>
        <button 
            @click="selectedCategory = 'commercial'"
            :class="selectedCategory === 'commercial' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
        >
            Commercial
        </button>
        <button 
            @click="selectedCategory = 'industrial'"
            :class="selectedCategory === 'industrial' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
        >
            Industrial
        </button>
        <button 
            @click="selectedCategory = 'construction'"
            :class="selectedCategory === 'construction' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
        >
            Construction
        </button>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <template x-for="template in filteredTemplates" :key="template.id">
            <div class="dashboard-card p-6 hover:shadow-lg transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900" x-text="template.name"></h3>
                        <p class="text-sm text-gray-600 capitalize" x-text="template.category"></p>
                    </div>
                    <div class="flex space-x-2">
                        <button 
                            @click="editTemplate(template)"
                            class="p-2 text-gray-400 hover:text-blue-600 transition-colors"
                        >
                            <i class="fas fa-edit"></i>
                        </button>
                        <button 
                            @click="deleteTemplate(template)"
                            class="p-2 text-gray-400 hover:text-red-600 transition-colors"
                        >
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <p class="text-gray-600 text-sm mb-4" x-text="template.description"></p>
                
                <!-- Phases Preview -->
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Design Phases:</h4>
                    <div class="flex flex-wrap gap-1">
                        <template x-for="(phase, key) in template.phases" :key="key">
                            <span 
                                class="px-2 py-1 text-xs rounded-full"
                                :class="getPhaseColorClass(phase.color)"
                                x-text="phase.name"
                            ></span>
                        </template>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex space-x-2">
                    <button 
                        @click="applyTemplate(template)"
                        class="flex-1 px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors"
                    >
                        <i class="fas fa-play mr-1"></i>Apply Template
                    </button>
                    <button 
                        @click="previewTemplate(template)"
                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- Create/Edit Template Modal -->
    <div x-show="showCreateModal || showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900">
                    <span x-text="showEditModal ? 'Edit Template' : 'Create New Template'"></span>
                </h2>
                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form @submit.prevent="saveTemplate()">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Info -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                            <input 
                                type="text" 
                                x-model="currentTemplate.name"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter template name"
                                required
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select 
                                x-model="currentTemplate.category"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea 
                                x-model="currentTemplate.description"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                rows="3"
                                placeholder="Enter template description"
                            ></textarea>
                        </div>
                    </div>
                    
                    <!-- Phases Management -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Design Phases</h3>
                            <button 
                                type="button"
                                @click="addPhase()"
                                class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors"
                            >
                                <i class="fas fa-plus mr-1"></i>Add Phase
                            </button>
                        </div>
                        
                        <div class="space-y-3 max-h-60 overflow-y-auto">
                            <template x-for="(phase, index) in currentTemplate.phases" :key="index">
                                <div class="border border-gray-200 rounded-lg p-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <input 
                                            type="text" 
                                            x-model="phase.name"
                                            class="flex-1 border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Phase name"
                                        >
                                        <button 
                                            type="button"
                                            @click="removePhase(index)"
                                            class="ml-2 p-1 text-red-600 hover:text-red-800"
                                        >
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-2">
                                        <select 
                                            x-model="phase.color"
                                            class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="blue">Blue</option>
                                            <option value="green">Green</option>
                                            <option value="orange">Orange</option>
                                            <option value="purple">Purple</option>
                                            <option value="pink">Pink</option>
                                            <option value="red">Red</option>
                                        </select>
                                        
                                        <input 
                                            type="number" 
                                            x-model="phase.duration"
                                            class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Duration (days)"
                                            min="1"
                                        >
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button 
                        type="button"
                        @click="closeModal()"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <span x-text="showEditModal ? 'Update Template' : 'Create Template'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Apply Template Modal -->
    <div x-show="showApplyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Apply Template</h2>
                <button @click="showApplyModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900 mb-2" x-text="selectedTemplate.name"></h3>
                <p class="text-gray-600 text-sm" x-text="selectedTemplate.description"></p>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Phases to Apply:</label>
                    <div class="space-y-2">
                        <template x-for="(phase, key) in selectedTemplate.phases" :key="key">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    x-model="selectedPhases"
                                    :value="key"
                                    class="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="text-sm text-gray-700" x-text="phase.name"></span>
                            </label>
                        </template>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Name</label>
                    <input 
                        type="text" 
                        x-model="newProjectName"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Enter project name"
                        required
                    >
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button 
                    @click="showApplyModal = false"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    @click="confirmApplyTemplate()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Apply Template
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function templateManager() {
    return {
        selectedCategory: 'all',
        showCreateModal: false,
        showEditModal: false,
        showApplyModal: false,
        selectedTemplate: {},
        selectedPhases: [],
        newProjectName: '',
        
        templates: [
            {
                id: 1,
                name: 'Residential Design Template',
                category: 'residential',
                description: 'Complete template for residential design projects including architectural, structural, MEP, and landscape design phases.',
                phases: {
                    architectural: {
                        name: 'Architectural Design',
                        color: 'blue',
                        duration: 45
                    },
                    structural: {
                        name: 'Structural Design',
                        color: 'green',
                        duration: 30
                    },
                    mep: {
                        name: 'MEP Design',
                        color: 'orange',
                        duration: 25
                    },
                    landscape: {
                        name: 'Landscape Design',
                        color: 'purple',
                        duration: 20
                    },
                    interior: {
                        name: 'Interior Design',
                        color: 'pink',
                        duration: 15
                    }
                }
            },
            {
                id: 2,
                name: 'Commercial Design Template',
                category: 'commercial',
                description: 'Comprehensive template for commercial and office building design projects.',
                phases: {
                    architectural: {
                        name: 'Architectural Design',
                        color: 'blue',
                        duration: 60
                    },
                    structural: {
                        name: 'Structural Design',
                        color: 'green',
                        duration: 40
                    },
                    mep: {
                        name: 'MEP Design',
                        color: 'orange',
                        duration: 35
                    }
                }
            },
            {
                id: 3,
                name: 'Residential Construction Template',
                category: 'construction',
                description: 'Complete template for residential construction projects with detailed phases.',
                phases: {
                    pre_construction: {
                        name: 'Pre-Construction',
                        color: 'gray',
                        duration: 28
                    },
                    foundation: {
                        name: 'Foundation & Structure',
                        color: 'brown',
                        duration: 57
                    },
                    mep_installation: {
                        name: 'MEP Installation',
                        color: 'blue',
                        duration: 50
                    },
                    interior: {
                        name: 'Interior Finishing',
                        color: 'orange',
                        duration: 44
                    },
                    exterior: {
                        name: 'Exterior & Landscaping',
                        color: 'green',
                        duration: 36
                    },
                    final_inspection: {
                        name: 'Final Inspection & Handover',
                        color: 'purple',
                        duration: 14
                    }
                }
            },
            {
                id: 4,
                name: 'Commercial Construction Template',
                category: 'construction',
                description: 'Comprehensive template for commercial construction projects.',
                phases: {
                    pre_construction: {
                        name: 'Pre-Construction',
                        color: 'gray',
                        duration: 42
                    },
                    foundation: {
                        name: 'Foundation & Structure',
                        color: 'brown',
                        duration: 87
                    },
                    mep_installation: {
                        name: 'MEP Installation',
                        color: 'blue',
                        duration: 86
                    },
                    interior: {
                        name: 'Interior Finishing',
                        color: 'orange',
                        duration: 75
                    },
                    exterior: {
                        name: 'Exterior & Site Work',
                        color: 'green',
                        duration: 61
                    },
                    final_inspection: {
                        name: 'Final Inspection & Handover',
                        color: 'purple',
                        duration: 30
                    }
                }
            }
        ],
        
        currentTemplate: {
            name: '',
            category: '',
            description: '',
            phases: []
        },
        
        get filteredTemplates() {
            if (this.selectedCategory === 'all') {
                return this.templates;
            }
            return this.templates.filter(template => template.category === this.selectedCategory);
        },
        
        getPhaseColorClass(color) {
            const colorMap = {
                blue: 'bg-blue-100 text-blue-800',
                green: 'bg-green-100 text-green-800',
                orange: 'bg-orange-100 text-orange-800',
                purple: 'bg-purple-100 text-purple-800',
                pink: 'bg-pink-100 text-pink-800',
                red: 'bg-red-100 text-red-800'
            };
            return colorMap[color] || 'bg-gray-100 text-gray-800';
        },
        
        addPhase() {
            this.currentTemplate.phases.push({
                name: '',
                color: 'blue',
                duration: 10
            });
        },
        
        removePhase(index) {
            this.currentTemplate.phases.splice(index, 1);
        },
        
        editTemplate(template) {
            this.currentTemplate = JSON.parse(JSON.stringify(template));
            this.currentTemplate.phases = Object.entries(template.phases).map(([key, phase]) => ({
                key,
                ...phase
            }));
            this.showEditModal = true;
        },
        
        deleteTemplate(template) {
            if (confirm(`Are you sure you want to delete "${template.name}"?`)) {
                const index = this.templates.findIndex(t => t.id === template.id);
                if (index > -1) {
                    this.templates.splice(index, 1);
                }
            }
        },
        
        applyTemplate(template) {
            this.selectedTemplate = template;
            this.selectedPhases = Object.keys(template.phases);
            this.newProjectName = '';
            this.showApplyModal = true;
        },
        
        previewTemplate(template) {
            alert(`Preview for "${template.name}" - Feature coming soon!`);
        },
        
        saveTemplate() {
            if (this.showEditModal) {
                // Update existing template
                const index = this.templates.findIndex(t => t.id === this.currentTemplate.id);
                if (index > -1) {
                    this.templates[index] = {
                        ...this.currentTemplate,
                        phases: this.currentTemplate.phases.reduce((acc, phase) => {
                            acc[phase.key || phase.name.toLowerCase().replace(/\s+/g, '_')] = {
                                name: phase.name,
                                color: phase.color,
                                duration: phase.duration
                            };
                            return acc;
                        }, {})
                    };
                }
            } else {
                // Create new template
                const newTemplate = {
                    id: Date.now(),
                    ...this.currentTemplate,
                    phases: this.currentTemplate.phases.reduce((acc, phase) => {
                        acc[phase.name.toLowerCase().replace(/\s+/g, '_')] = {
                            name: phase.name,
                            color: phase.color,
                            duration: phase.duration
                        };
                        return acc;
                    }, {})
                };
                this.templates.push(newTemplate);
            }
            
            this.closeModal();
        },
        
        confirmApplyTemplate() {
            if (!this.newProjectName.trim()) {
                alert('Please enter a project name');
                return;
            }
            
            if (this.selectedPhases.length === 0) {
                alert('Please select at least one phase');
                return;
            }
            
            // Apply template logic here
            alert(`Template "${this.selectedTemplate.name}" applied to project "${this.newProjectName}" with phases: ${this.selectedPhases.join(', ')}`);
            this.showApplyModal = false;
        },
        
        closeModal() {
            this.showCreateModal = false;
            this.showEditModal = false;
            this.currentTemplate = {
                name: '',
                category: '',
                description: '',
                phases: []
            };
        },
        
        goToBuilder() {
            window.location.href = '/templates/builder';
        },
        
        goToConstructionBuilder() {
            window.location.href = '/templates/construction-builder';
        },
        
        goToAnalytics() {
            window.location.href = '/templates/analytics';
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/templates/index.blade.php ENDPATH**/ ?>