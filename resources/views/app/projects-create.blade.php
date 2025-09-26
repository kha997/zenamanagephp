<!-- Project Creation Form -->
<div x-data="projectForm()" class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create New Project</h1>
                <p class="mt-1 text-sm text-gray-500">Start a new project and track its progress</p>
            </div>
            <button @click="goBack()" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Projects
            </button>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white shadow rounded-lg">
        <form @submit.prevent="submitForm()" class="p-6 space-y-6">
            <!-- Basic Information -->
            <div class="border-b border-gray-200 pb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Project Name -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Project Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               x-model="form.name"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter project name"
                               required>
                        <div x-show="errors.name" class="mt-1 text-sm text-red-600" x-text="errors.name"></div>
                    </div>

                    <!-- Project Code -->
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                            Project Code
                        </label>
                        <input type="text" 
                               id="code" 
                               x-model="form.code"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Auto-generated">
                        <p class="mt-1 text-xs text-gray-500">Leave empty for auto-generation</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status
                        </label>
                        <select id="status" 
                                x-model="form.status"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Start Date
                        </label>
                        <input type="date" 
                               id="start_date" 
                               x-model="form.start_date"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- End Date -->
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
                            End Date
                        </label>
                        <input type="date" 
                               id="end_date" 
                               x-model="form.end_date"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Budget -->
                    <div>
                        <label for="budget_total" class="block text-sm font-medium text-gray-700 mb-2">
                            Budget Total
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input type="number" 
                                   id="budget_total" 
                                   x-model="form.budget_total"
                                   step="0.01"
                                   min="0"
                                   class="w-full border border-gray-300 rounded-md pl-8 pr-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="0.00">
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea id="description" 
                              x-model="form.description"
                              rows="4"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Describe the project goals, scope, and requirements..."></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                <button type="button" 
                        @click="goBack()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        :disabled="loading"
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!loading">Create Project</span>
                    <span x-show="loading" class="flex items-center">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Creating...
                    </span>
                </button>
            </div>
        </form>
    </div>

    <!-- Success Message -->
    <div x-show="success" 
         x-transition
         class="mt-4 bg-green-50 border border-green-200 rounded-md p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-green-800">
                    Project created successfully!
                </h3>
                <div class="mt-2 text-sm text-green-700">
                    <p>Your project has been created and is ready to use.</p>
                </div>
                <div class="mt-4">
                    <button @click="goToProject()" 
                            class="bg-green-100 text-green-800 px-3 py-2 rounded text-sm hover:bg-green-200">
                        View Project
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Message -->
    <div x-show="error" 
         x-transition
         class="mt-4 bg-red-50 border border-red-200 rounded-md p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">
                    Error creating project
                </h3>
                <div class="mt-2 text-sm text-red-700">
                    <p x-text="error"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function projectForm() {
    return {
        loading: false,
        success: false,
        error: null,
        createdProjectId: null,
        errors: {},
        
        form: {
            name: '',
            code: '',
            description: '',
            status: 'planning',
            start_date: '',
            end_date: '',
            budget_total: ''
        },

        async submitForm() {
            this.loading = true;
            this.error = null;
            this.errors = {};
            this.success = false;

            try {
                // Get auth token
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                // Prepare form data
                const formData = { ...this.form };
                if (formData.budget_total === '') formData.budget_total = null;
                if (formData.start_date === '') formData.start_date = null;
                if (formData.end_date === '') formData.end_date = null;
                if (formData.code === '') delete formData.code;

                const response = await fetch('/app/api/v1/app/projects', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.status === 'success') {
                    this.success = true;
                    this.createdProjectId = data.data.project.id;
                    this.form = {
                        name: '',
                        code: '',
                        description: '',
                        status: 'planning',
                        start_date: '',
                        end_date: '',
                        budget_total: ''
                    };
                } else {
                    if (data.errors) {
                        this.errors = data.errors;
                    } else {
                        this.error = data.message || 'Failed to create project';
                    }
                }

            } catch (error) {
                console.error('Error creating project:', error);
                this.error = error.message || 'An unexpected error occurred';
            } finally {
                this.loading = false;
            }
        },

        goBack() {
            window.location.href = '/app/projects';
        },

        goToProject() {
            if (this.createdProjectId) {
                window.location.href = `/app/projects/${this.createdProjectId}`;
            } else {
                this.goBack();
            }
        }
    }
}
</script>
