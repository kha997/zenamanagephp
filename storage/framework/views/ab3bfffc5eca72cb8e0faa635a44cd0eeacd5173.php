<?php $__env->startSection('title', 'Users'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Users</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Users</h1>
            <p class="text-gray-600">Manage all system users</p>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="exportUsers" 
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button @click="openCreateModal" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>New User
            </button>
        </div>
    </div>
    
    
    <?php echo $__env->make('admin.users._filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.users._table', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.users._pagination', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function usersPage() {
        return {
            users: [
                {
                    id: 1,
                    name: 'John Doe',
                    email: 'john@techcorp.com',
                    tenant: 'TechCorp',
                    role: 'Admin',
                    status: 'active',
                    lastLogin: '2024-09-27',
                    createdAt: '2024-01-15'
                },
                {
                    id: 2,
                    name: 'Jane Smith',
                    email: 'jane@designstudio.com',
                    tenant: 'DesignStudio',
                    role: 'Project Manager',
                    status: 'active',
                    lastLogin: '2024-09-26',
                    createdAt: '2024-02-20'
                },
                {
                    id: 3,
                    name: 'Mike Johnson',
                    email: 'mike@startupxyz.com',
                    tenant: 'StartupXYZ',
                    role: 'Member',
                    status: 'inactive',
                    lastLogin: '2024-09-20',
                    createdAt: '2024-03-10'
                }
            ],
            
            filteredUsers: [],
            searchQuery: '',
            statusFilter: 'all',
            roleFilter: 'all',
            tenantFilter: 'all',
            sortBy: 'name',
            sortOrder: 'asc',
            selectedUsers: [],
            showCreateModal: false,
            showEditModal: false,
            showDeleteModal: false,
            currentUser: null,
            
            init() {
                this.filteredUsers = [...this.users];
                this.sortUsers();
            },
            
            filterUsers() {
                this.filteredUsers = this.users.filter(user => {
                    const matchesSearch = user.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                        user.email.toLowerCase().includes(this.searchQuery.toLowerCase());
                    
                    const matchesStatus = this.statusFilter === 'all' || user.status === this.statusFilter;
                    const matchesRole = this.roleFilter === 'all' || user.role === this.roleFilter;
                    const matchesTenant = this.tenantFilter === 'all' || user.tenant === this.tenantFilter;
                    
                    return matchesSearch && matchesStatus && matchesRole && matchesTenant;
                });
                
                this.sortUsers();
            },
            
            sortUsers() {
                this.filteredUsers.sort((a, b) => {
                    let aValue = a[this.sortBy];
                    let bValue = b[this.sortBy];
                    
                    if (typeof aValue === 'string') {
                        aValue = aValue.toLowerCase();
                        bValue = bValue.toLowerCase();
                    }
                    
                    if (this.sortOrder === 'asc') {
                        return aValue > bValue ? 1 : -1;
                    } else {
                        return aValue < bValue ? 1 : -1;
                    }
                });
            },
            
            setSort(column) {
                if (this.sortBy === column) {
                    this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = column;
                    this.sortOrder = 'asc';
                }
                this.sortUsers();
            },
            
            selectUser(user) {
                const index = this.selectedUsers.findIndex(u => u.id === user.id);
                if (index > -1) {
                    this.selectedUsers.splice(index, 1);
                } else {
                    this.selectedUsers.push(user);
                }
            },
            
            selectAllUsers() {
                if (this.selectedUsers.length === this.filteredUsers.length) {
                    this.selectedUsers = [];
                } else {
                    this.selectedUsers = [...this.filteredUsers];
                }
            },
            
            openCreateModal() {
                this.showCreateModal = true;
                this.currentUser = {
                    name: '',
                    email: '',
                    tenant: '',
                    role: 'Member'
                };
            },
            
            openEditModal(user) {
                this.showEditModal = true;
                this.currentUser = { ...user };
            },
            
            openDeleteModal(user) {
                this.showDeleteModal = true;
                this.currentUser = user;
            },
            
            closeModals() {
                this.showCreateModal = false;
                this.showEditModal = false;
                this.showDeleteModal = false;
                this.currentUser = null;
            },
            
            saveUser() {
                if (this.showCreateModal) {
                    // Create new user
                    const newUser = {
                        ...this.currentUser,
                        id: this.users.length + 1,
                        status: 'active',
                        lastLogin: null,
                        createdAt: new Date().toISOString().split('T')[0]
                    };
                    this.users.push(newUser);
                } else if (this.showEditModal) {
                    // Update existing user
                    const index = this.users.findIndex(u => u.id === this.currentUser.id);
                    if (index > -1) {
                        this.users[index] = { ...this.currentUser };
                    }
                }
                
                this.closeModals();
                this.filterUsers();
            },
            
            deleteUser() {
                const index = this.users.findIndex(u => u.id === this.currentUser.id);
                if (index > -1) {
                    this.users.splice(index, 1);
                }
                this.closeModals();
                this.filterUsers();
            },
            
            bulkAction(action) {
                if (this.selectedUsers.length === 0) return;
                
                switch(action) {
                    case 'activate':
                        this.selectedUsers.forEach(user => {
                            const index = this.users.findIndex(u => u.id === user.id);
                            if (index > -1) {
                                this.users[index].status = 'active';
                            }
                        });
                        break;
                    case 'deactivate':
                        this.selectedUsers.forEach(user => {
                            const index = this.users.findIndex(u => u.id === user.id);
                            if (index > -1) {
                                this.users[index].status = 'inactive';
                            }
                        });
                        break;
                    case 'delete':
                        this.selectedUsers.forEach(user => {
                            const index = this.users.findIndex(u => u.id === user.id);
                            if (index > -1) {
                                this.users.splice(index, 1);
                            }
                        });
                        break;
                }
                
                this.selectedUsers = [];
                this.filterUsers();
            },
            
            exportUsers() {
                console.log('Exporting users...');
            },
            
            resetPassword(user) {
                console.log('Resetting password for:', user.email);
                // In real implementation, this would send reset email
            }
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/users/index.blade.php ENDPATH**/ ?>