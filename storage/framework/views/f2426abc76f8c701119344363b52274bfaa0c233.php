<?php $__env->startSection('title', 'Tenants'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Tenants</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tenants</h1>
            <p class="text-gray-600">Manage all tenant organizations</p>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="exportTenants" 
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button @click="openCreateModal" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>New Tenant
            </button>
        </div>
    </div>
    
    
    <?php echo $__env->make('admin.tenants._filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.tenants._table', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.tenants._pagination', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function tenantsPage() {
        return {
            tenants: [
                {
                    id: 1,
                    name: 'TechCorp',
                    domain: 'techcorp.com',
                    owner: 'John Doe',
                    ownerEmail: 'john@techcorp.com',
                    plan: 'Professional',
                    status: 'active',
                    users: 25,
                    createdAt: '2024-01-15',
                    lastActive: '2024-09-27'
                },
                {
                    id: 2,
                    name: 'DesignStudio',
                    domain: 'designstudio.com',
                    owner: 'Jane Smith',
                    ownerEmail: 'jane@designstudio.com',
                    plan: 'Basic',
                    status: 'active',
                    users: 8,
                    createdAt: '2024-02-20',
                    lastActive: '2024-09-26'
                },
                {
                    id: 3,
                    name: 'StartupXYZ',
                    domain: 'startupxyz.com',
                    owner: 'Mike Johnson',
                    ownerEmail: 'mike@startupxyz.com',
                    plan: 'Enterprise',
                    status: 'suspended',
                    users: 45,
                    createdAt: '2024-03-10',
                    lastActive: '2024-09-20'
                }
            ],
            
            filteredTenants: [],
            searchQuery: '',
            statusFilter: 'all',
            planFilter: 'all',
            sortBy: 'name',
            sortOrder: 'asc',
            selectedTenants: [],
            showCreateModal: false,
            showEditModal: false,
            showDeleteModal: false,
            currentTenant: null,
            
            init() {
                this.filteredTenants = [...this.tenants];
                this.sortTenants();
            },
            
            filterTenants() {
                this.filteredTenants = this.tenants.filter(tenant => {
                    const matchesSearch = tenant.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                        tenant.domain.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                        tenant.owner.toLowerCase().includes(this.searchQuery.toLowerCase());
                    
                    const matchesStatus = this.statusFilter === 'all' || tenant.status === this.statusFilter;
                    const matchesPlan = this.planFilter === 'all' || tenant.plan === this.planFilter;
                    
                    return matchesSearch && matchesStatus && matchesPlan;
                });
                
                this.sortTenants();
            },
            
            sortTenants() {
                this.filteredTenants.sort((a, b) => {
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
                this.sortTenants();
            },
            
            selectTenant(tenant) {
                const index = this.selectedTenants.findIndex(t => t.id === tenant.id);
                if (index > -1) {
                    this.selectedTenants.splice(index, 1);
                } else {
                    this.selectedTenants.push(tenant);
                }
            },
            
            selectAllTenants() {
                if (this.selectedTenants.length === this.filteredTenants.length) {
                    this.selectedTenants = [];
                } else {
                    this.selectedTenants = [...this.filteredTenants];
                }
            },
            
            openCreateModal() {
                this.showCreateModal = true;
                this.currentTenant = {
                    name: '',
                    domain: '',
                    owner: '',
                    ownerEmail: '',
                    plan: 'Basic'
                };
            },
            
            openEditModal(tenant) {
                this.showEditModal = true;
                this.currentTenant = { ...tenant };
            },
            
            openDeleteModal(tenant) {
                this.showDeleteModal = true;
                this.currentTenant = tenant;
            },
            
            closeModals() {
                this.showCreateModal = false;
                this.showEditModal = false;
                this.showDeleteModal = false;
                this.currentTenant = null;
            },
            
            saveTenant() {
                if (this.showCreateModal) {
                    // Create new tenant
                    const newTenant = {
                        ...this.currentTenant,
                        id: this.tenants.length + 1,
                        status: 'active',
                        users: 0,
                        createdAt: new Date().toISOString().split('T')[0],
                        lastActive: new Date().toISOString().split('T')[0]
                    };
                    this.tenants.push(newTenant);
                } else if (this.showEditModal) {
                    // Update existing tenant
                    const index = this.tenants.findIndex(t => t.id === this.currentTenant.id);
                    if (index > -1) {
                        this.tenants[index] = { ...this.currentTenant };
                    }
                }
                
                this.closeModals();
                this.filterTenants();
            },
            
            deleteTenant() {
                const index = this.tenants.findIndex(t => t.id === this.currentTenant.id);
                if (index > -1) {
                    this.tenants.splice(index, 1);
                }
                this.closeModals();
                this.filterTenants();
            },
            
            bulkAction(action) {
                if (this.selectedTenants.length === 0) return;
                
                switch(action) {
                    case 'suspend':
                        this.selectedTenants.forEach(tenant => {
                            const index = this.tenants.findIndex(t => t.id === tenant.id);
                            if (index > -1) {
                                this.tenants[index].status = 'suspended';
                            }
                        });
                        break;
                    case 'activate':
                        this.selectedTenants.forEach(tenant => {
                            const index = this.tenants.findIndex(t => t.id === tenant.id);
                            if (index > -1) {
                                this.tenants[index].status = 'active';
                            }
                        });
                        break;
                    case 'delete':
                        this.selectedTenants.forEach(tenant => {
                            const index = this.tenants.findIndex(t => t.id === tenant.id);
                            if (index > -1) {
                                this.tenants.splice(index, 1);
                            }
                        });
                        break;
                }
                
                this.selectedTenants = [];
                this.filterTenants();
            },
            
            exportTenants() {
                console.log('Exporting tenants...');
                // In real implementation, this would generate and download CSV/Excel
            }
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/index.blade.php ENDPATH**/ ?>