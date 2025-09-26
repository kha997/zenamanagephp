<!-- Team Content - Modern Design System -->
<style>
    [x-cloak] { display: none !important; }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    
    /* Team Member Card Animations */
    .member-card {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .member-card:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    /* Role Badge Colors */
    .role-admin { @apply bg-red-100 text-red-800 border-red-200; }
    .role-manager { @apply bg-blue-100 text-blue-800 border-blue-200; }
    .role-developer { @apply bg-green-100 text-green-800 border-green-200; }
    .role-designer { @apply bg-purple-100 text-purple-800 border-purple-200; }
    .role-marketing { @apply bg-yellow-100 text-yellow-800 border-yellow-200; }
    .role-support { @apply bg-gray-100 text-gray-800 border-gray-200; }
    
    /* Status Indicators */
    .status-online { @apply bg-green-500; }
    .status-away { @apply bg-yellow-500; }
    .status-busy { @apply bg-red-500; }
    .status-offline { @apply bg-gray-400; }
    
    /* Avatar Styles */
    .avatar {
        position: relative;
        display: inline-block;
    }
    
    .avatar-status {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid white;
    }
</style>

<div x-data="teamPage()" x-init="loadTeamMembers()" class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Team</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your team members and their roles</p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-3">
            <button @click="showFilters = !showFilters" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-filter mr-2"></i>
                Filters
            </button>
            <button @click="showInviteModal = true" 
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-user-plus mr-2"></i>
                Invite Member
            </button>
        </div>
    </div>

    <!-- Team Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-users text-2xl text-blue-500"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Members</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="teamStats.totalMembers"></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-circle text-green-500 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Online Now</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="teamStats.onlineMembers"></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user-check text-2xl text-green-500"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Active Projects</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="teamStats.activeProjects"></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-tasks text-2xl text-yellow-500"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tasks Completed</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="teamStats.completedTasks"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Search & Filters -->
    <div class="bg-white rounded-lg shadow p-6">
        <!-- Search Bar -->
        <div class="relative mb-4">
            <input type="text" 
                   placeholder="Search team members..." 
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   x-model="searchQuery"
                   @input="debounceSearch()">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>

        <!-- Filter Panel -->
        <div x-show="showFilters" x-transition class="border-t pt-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Role Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select x-model="filters.role" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="developer">Developer</option>
                        <option value="designer">Designer</option>
                        <option value="marketing">Marketing</option>
                        <option value="support">Support</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select x-model="filters.status" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="online">Online</option>
                        <option value="away">Away</option>
                        <option value="busy">Busy</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>

                <!-- Department Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select x-model="filters.department" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Departments</option>
                        <option value="engineering">Engineering</option>
                        <option value="design">Design</option>
                        <option value="marketing">Marketing</option>
                        <option value="sales">Sales</option>
                        <option value="support">Support</option>
                    </select>
                </div>
            </div>

            <!-- Active Filters -->
            <div x-show="activeFiltersCount > 0" class="mt-4">
                <div class="flex flex-wrap gap-2">
                    <template x-for="filter in activeFilters" :key="filter.key">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <span x-text="filter.label + ': ' + filter.value"></span>
                            <button @click="removeFilter(filter.key)" class="ml-2 hover:text-blue-600">×</button>
                        </span>
                    </template>
                    <button @click="clearAllFilters()" 
                            class="text-sm text-gray-500 hover:text-gray-700 underline">
                        Clear all
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="space-y-4">
        <template x-for="i in 6" :key="i">
            <div class="bg-white rounded-lg shadow p-6 animate-pulse">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gray-200 rounded-full"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                    <div class="w-20 h-6 bg-gray-200 rounded"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading team members</p>
                <p x-text="error"></p>
                <button @click="loadTeamMembers()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Team Members Grid -->
    <div x-show="!loading && !error" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <template x-for="member in filteredMembers" :key="member.id">
            <div class="member-card bg-white rounded-lg shadow p-6 cursor-pointer" 
                 @click="viewMember(member.id)">
                
                <!-- Member Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="avatar">
                            <img :src="member.avatar" 
                                 :alt="member.name"
                                 class="w-12 h-12 rounded-full object-cover">
                            <div class="avatar-status" :class="'status-' + member.status"></div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900" x-text="member.name"></h3>
                            <p class="text-sm text-gray-500" x-text="member.email"></p>
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full border"
                          :class="'role-' + member.role"
                          x-text="member.role"></span>
                </div>

                <!-- Member Info -->
                <div class="space-y-2 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-building mr-2"></i>
                        <span x-text="member.department"></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-calendar mr-2"></i>
                        <span x-text="'Joined ' + member.joinedAt"></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span x-text="member.location"></span>
                    </div>
                </div>

                <!-- Member Stats -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="text-center">
                        <div class="text-xl font-bold text-blue-600" x-text="member.activeProjects"></div>
                        <div class="text-xs text-gray-500">Active Projects</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-green-600" x-text="member.completedTasks"></div>
                        <div class="text-xs text-gray-500">Completed Tasks</div>
                    </div>
                </div>

                <!-- Member Skills -->
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Skills</h4>
                    <div class="flex flex-wrap gap-1">
                        <template x-for="skill in member.skills" :key="skill">
                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded"
                                  x-text="skill"></span>
                        </template>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-2">
                    <button @click.stop="editMember(member.id)" 
                            class="flex-1 bg-gray-100 text-gray-700 px-3 py-2 rounded text-sm hover:bg-gray-200">
                        <i class="fas fa-edit mr-1"></i>
                        Edit
                    </button>
                    <button @click.stop="messageMember(member.id)" 
                            class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700">
                        <i class="fas fa-message mr-1"></i>
                        Message
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div x-show="!loading && !error && filteredMembers.length === 0" class="text-center py-12">
        <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No team members found</h3>
        <p class="text-gray-500 mb-4">Get started by inviting your first team member.</p>
        <button @click="showInviteModal = true" 
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class="fas fa-user-plus mr-2"></i>
            Invite Member
        </button>
    </div>
</div>

<script>
function teamPage() {
    return {
        loading: true,
        error: null,
        members: [],
        filteredMembers: [],
        teamStats: {
            totalMembers: 0,
            onlineMembers: 0,
            activeProjects: 0,
            completedTasks: 0
        },
        searchQuery: '',
        showFilters: false,
        showInviteModal: false,
        filters: {
            role: '',
            status: '',
            department: ''
        },
        activeFilters: [],
        activeFiltersCount: 0,
        searchTimeout: null,

        async init() {
            console.log('🚀 Team page init started');
            await this.loadTeamMembers();
        },

        async loadTeamMembers() {
            try {
                this.loading = true;
                this.error = null;
                console.log('📊 Loading team members data...');
                
                // Get auth token
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                // Fetch real data from API (mock for now)
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // Mock data
                this.members = [
                    {
                        id: 1,
                        name: 'John Doe',
                        email: 'john.doe@company.com',
                        role: 'admin',
                        status: 'online',
                        department: 'Engineering',
                        joinedAt: '2023-01-15',
                        location: 'San Francisco, CA',
                        avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face',
                        activeProjects: 3,
                        completedTasks: 45,
                        skills: ['React', 'Node.js', 'Python', 'AWS']
                    },
                    {
                        id: 2,
                        name: 'Jane Smith',
                        email: 'jane.smith@company.com',
                        role: 'manager',
                        status: 'away',
                        department: 'Design',
                        joinedAt: '2023-02-20',
                        location: 'New York, NY',
                        avatar: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?w=100&h=100&fit=crop&crop=face',
                        activeProjects: 2,
                        completedTasks: 38,
                        skills: ['UI/UX', 'Figma', 'Sketch', 'Photoshop']
                    },
                    {
                        id: 3,
                        name: 'Mike Johnson',
                        email: 'mike.johnson@company.com',
                        role: 'developer',
                        status: 'busy',
                        department: 'Engineering',
                        joinedAt: '2023-03-10',
                        location: 'Austin, TX',
                        avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face',
                        activeProjects: 4,
                        completedTasks: 52,
                        skills: ['JavaScript', 'TypeScript', 'Vue.js', 'Docker']
                    },
                    {
                        id: 4,
                        name: 'Sarah Wilson',
                        email: 'sarah.wilson@company.com',
                        role: 'designer',
                        status: 'online',
                        department: 'Design',
                        joinedAt: '2023-04-05',
                        location: 'Seattle, WA',
                        avatar: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop&crop=face',
                        activeProjects: 2,
                        completedTasks: 29,
                        skills: ['Illustrator', 'InDesign', 'After Effects', 'Blender']
                    },
                    {
                        id: 5,
                        name: 'Tom Brown',
                        email: 'tom.brown@company.com',
                        role: 'marketing',
                        status: 'offline',
                        department: 'Marketing',
                        joinedAt: '2023-05-12',
                        location: 'Chicago, IL',
                        avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face',
                        activeProjects: 1,
                        completedTasks: 33,
                        skills: ['SEO', 'Google Analytics', 'Content Marketing', 'Social Media']
                    },
                    {
                        id: 6,
                        name: 'Lisa Davis',
                        email: 'lisa.davis@company.com',
                        role: 'support',
                        status: 'online',
                        department: 'Support',
                        joinedAt: '2023-06-18',
                        location: 'Miami, FL',
                        avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&h=100&fit=crop&crop=face',
                        activeProjects: 0,
                        completedTasks: 67,
                        skills: ['Customer Service', 'Zendesk', 'Salesforce', 'Communication']
                    }
                ];
                
                // Calculate team stats
                this.teamStats = {
                    totalMembers: this.members.length,
                    onlineMembers: this.members.filter(m => m.status === 'online').length,
                    activeProjects: this.members.reduce((sum, m) => sum + m.activeProjects, 0),
                    completedTasks: this.members.reduce((sum, m) => sum + m.completedTasks, 0)
                };
                
                this.applyFilters();
                this.loading = false;
                console.log('✅ Team members data loaded successfully');
                
            } catch (error) {
                console.error('❌ Error loading team members data:', error);
                this.error = error.message;
                this.loading = false;
            }
        },

        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.applyFilters();
            }, 300);
        },

        applyFilters() {
            let filtered = [...this.members];
            
            // Apply search filter
            if (this.searchQuery) {
                filtered = filtered.filter(member => 
                    member.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    member.email.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    member.department.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    member.skills.some(skill => skill.toLowerCase().includes(this.searchQuery.toLowerCase()))
                );
            }
            
            // Apply role filter
            if (this.filters.role) {
                filtered = filtered.filter(member => member.role === this.filters.role);
            }
            
            // Apply status filter
            if (this.filters.status) {
                filtered = filtered.filter(member => member.status === this.filters.status);
            }
            
            // Apply department filter
            if (this.filters.department) {
                filtered = filtered.filter(member => member.department.toLowerCase().includes(this.filters.department.toLowerCase()));
            }
            
            this.filteredMembers = filtered;
            this.updateActiveFilters();
        },

        updateActiveFilters() {
            this.activeFilters = [];
            
            if (this.filters.role) {
                this.activeFilters.push({
                    key: 'role',
                    label: 'Role',
                    value: this.filters.role
                });
            }
            
            if (this.filters.status) {
                this.activeFilters.push({
                    key: 'status',
                    label: 'Status',
                    value: this.filters.status
                });
            }
            
            if (this.filters.department) {
                this.activeFilters.push({
                    key: 'department',
                    label: 'Department',
                    value: this.filters.department
                });
            }
            
            this.activeFiltersCount = this.activeFilters.length;
        },

        removeFilter(filterKey) {
            this.filters[filterKey] = '';
            this.applyFilters();
        },

        clearAllFilters() {
            this.filters = {
                role: '',
                status: '',
                department: ''
            };
            this.searchQuery = '';
            this.applyFilters();
        },

        viewMember(memberId) {
            console.log('Viewing member:', memberId);
            window.location.href = `/app/team/${memberId}`;
        },

        editMember(memberId) {
            console.log('Editing member:', memberId);
            window.location.href = `/app/team/${memberId}/edit`;
        },

        messageMember(memberId) {
            console.log('Messaging member:', memberId);
            // Implement messaging functionality
        }
    }
}
</script><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/team-content.blade.php ENDPATH**/ ?>