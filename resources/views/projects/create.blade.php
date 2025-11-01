@extends('layouts.dashboard')

@section('title', 'Create Project')
@section('page-title', 'Create Project')
@section('page-description', 'Start a new project with detailed planning')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')

@section('content')
<div x-data="projectCreate()">
    <!-- Template Selection -->
    <div class="zena-card zena-p-lg zena-mb-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">🎯 Choose Project Template</h3>
        <p class="text-gray-600 mb-4">Select a template to automatically populate project phases and tasks, or start from scratch.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- No Template Option -->
            <div 
                @click="selectedTemplate = null"
                :class="selectedTemplate === null ? 'ring-2 ring-blue-500 bg-blue-50' : 'hover:bg-gray-50'"
                class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-colors"
            >
                <div class="text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                        <i class="fas fa-file text-gray-600"></i>
                    </div>
                    <h4 class="font-medium text-gray-900">Start from Scratch</h4>
                    <p class="text-sm text-gray-600">Create project without template</p>
                </div>
            </div>
            
            <!-- Template Options -->
            <template x-for="template in templates" :key="template.id">
                <div 
                    @click="selectedTemplate = template"
                    :class="selectedTemplate?.id === template.id ? 'ring-2 ring-blue-500 bg-blue-50' : 'hover:bg-gray-50'"
                    class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-colors"
                >
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <i class="fas fa-layer-group text-blue-600"></i>
                        </div>
                        <h4 class="font-medium text-gray-900" x-text="template.name"></h4>
                        <p class="text-sm text-gray-600 capitalize" x-text="template.category"></p>
                        <div class="mt-2">
                            <span class="text-xs text-gray-500" x-text="Object.keys(template.phases).length + ' phases'"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        
        <!-- Selected Template Preview -->
        <div x-show="selectedTemplate" class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-medium text-gray-900 mb-2">Selected Template: <span x-text="selectedTemplate?.name"></span></h4>
            <p class="text-sm text-gray-600 mb-3" x-text="selectedTemplate?.description"></p>
            <div class="flex flex-wrap gap-2">
                <template x-for="(phase, key) in selectedTemplate?.phases" :key="key">
                    <span 
                        class="zena-badge"
                        :class="getPhaseColorClass(phase.color)"
                        x-text="phase.name"
                    ></span>
                </template>
            </div>
        </div>
    </div>

    <!-- Project Creation Form -->
    <div class="zena-card zena-p-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">📋 Create New Project</h3>
        
        <form method="POST" action="/projects" @submit.prevent="createProject">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Project Basic Info -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Basic Information</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Name *</label>
                            <input 
                                type="text" 
                                name="name" 
                                required 
                                class="zena-input"
                                placeholder="Enter project name"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Description</label>
                            <textarea 
                                name="description" 
                                rows="3"
                                class="zena-textarea"
                                placeholder="Describe the project..."
                            ></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date *</label>
                                <input 
                                    type="date" 
                                    name="start_date" 
                                    required 
                                    class="zena-input"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">End Date *</label>
                                <input 
                                    type="date" 
                                    name="end_date" 
                                    required 
                                    class="zena-input"
                                >
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Details -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Project Details</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Status</label>
                            <select name="status" class="zena-select">
                                <option value="planning">Planning</option>
                                <option value="active">Active</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select name="priority" class="zena-select">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Budget</label>
                            <input 
                                type="number" 
                                name="budget" 
                                class="zena-input"
                                placeholder="Enter project budget"
                            >
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Team Assignment -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Team Assignment</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Manager</label>
                            <select name="pm_id" class="zena-select">
                                <option value="">Select Project Manager</option>
                                <option value="1">John Smith</option>
                                <option value="2">Sarah Wilson</option>
                                <option value="3">Mike Johnson</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Team Members</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" name="team_members[]" value="1" class="mr-2">
                                    <span class="text-sm">John Smith (Project Manager)</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="team_members[]" value="2" class="mr-2">
                                    <span class="text-sm">Sarah Wilson (Designer)</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="team_members[]" value="3" class="mr-2">
                                    <span class="text-sm">Mike Johnson (Developer)</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="team_members[]" value="4" class="mr-2">
                                    <span class="text-sm">Alex Lee (Site Engineer)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Client Information -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Client Information</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client Name</label>
                            <input 
                                type="text" 
                                name="client_name" 
                                class="zena-input"
                                placeholder="Enter client name"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client Email</label>
                            <input 
                                type="email" 
                                name="client_email" 
                                class="zena-input"
                                placeholder="Enter client email"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client Phone</label>
                            <input 
                                type="tel" 
                                name="client_phone" 
                                class="zena-input"
                                placeholder="Enter client phone"
                            >
                        </div>
                    </div>
                    
                    <!-- Project Settings -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Project Settings</h4>
                        
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="checkbox" name="notifications" value="1" class="mr-2" checked>
                                <span class="text-sm">Enable email notifications</span>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="public" value="1" class="mr-2">
                                <span class="text-sm">Make project public</span>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="tracking" value="1" class="mr-2" checked>
                                <span class="text-sm">Enable time tracking</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                <button 
                    type="button" 
                    @click="cancelCreate()"
                    class="zena-btn zena-btn-outline"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="zena-btn zena-btn-primary"
                    :disabled="creating"
                >
                    <span x-show="!creating">🚀 Create Project</span>
                    <span x-show="creating">⏳ Creating...</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function projectCreate() {
    return {
        creating: false,
        selectedTemplate: null,
        
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
        
        getPhaseColorClass(color) {
            const colorMap = {
                blue: 'zena-badge-info',
                green: 'zena-badge-success',
                orange: 'zena-badge-warning',
                purple: 'zena-badge-info',
                pink: 'zena-badge-info',
                red: 'zena-badge-danger'
            };
            return colorMap[color] || 'zena-badge-neutral';
        },
        
        createProject() {
            this.creating = true;
            
            // If template is selected, show template info
            if (this.selectedTemplate) {
                alert(`Creating project with template: ${this.selectedTemplate.name}\nPhases: ${Object.keys(this.selectedTemplate.phases).join(', ')}`);
            }
            
            // Form submission will be handled by Laravel
            // This is just for UI feedback
            setTimeout(() => {
                this.creating = false;
                alert('Project created successfully!');
                window.location.href = '/projects';
            }, 2000);
        },
        
        cancelCreate() {
            window.location.href = '/projects';
        }
    }
}
</script>
@endsection