


<div class="responsive-table" x-data="responsiveTable()">
    <!-- Desktop Table View -->
    <div class="hidden md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <template x-for="(column, index) in columns" :key="'header-' + (column.key || index)">
                            <th scope="col" 
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                :class="column.sortable ? 'cursor-pointer hover:bg-gray-100' : ''"
                                @click="column.sortable ? sortBy(column.key) : null">
                                <div class="flex items-center space-x-1">
                                    <span x-text="column.label"></span>
                                    <template x-if="column.sortable">
                                        <i class="fas fa-sort text-gray-400" 
                                           :class="sortField === column.key ? (sortDirection === 'asc' ? 'fa-sort-up text-blue-500' : 'fa-sort-down text-blue-500') : ''"></i>
                                    </template>
                                </div>
                            </th>
                        </template>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="(row, index) in paginatedData" :key="'table-row-' + row.id">
                        <tr class="hover:bg-gray-50">
                            <template x-for="(column, index) in columns" :key="'body-' + (column.key || index)">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <template x-if="column.type === 'text'">
                                        <span x-text="row[column.key]"></span>
                                    </template>
                                    <template x-if="column.type === 'badge'">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                              :class="getBadgeClass(row[column.key])"
                                              x-text="row[column.key]"></span>
                                    </template>
                                    <template x-if="column.type === 'progress'">
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" 
                                                     :style="`width: ${row[column.key]}%`"></div>
                                            </div>
                                            <span class="ml-2 text-xs text-gray-500" x-text="row[column.key] + '%'"></span>
                                        </div>
                                    </template>
                                    <template x-if="column.type === 'date'">
                                        <span x-text="formatDate(row[column.key])"></span>
                                    </template>
                                    <template x-if="column.type === 'avatar'">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                                <span class="text-white text-xs font-medium" 
                                                      x-text="getInitials(row[column.key])"></span>
                                            </div>
                                            <span class="ml-2 text-sm text-gray-900" x-text="row[column.key]"></span>
                                        </div>
                                    </template>
                                </td>
                            </template>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <template x-for="action in rowActions" :key="'table-' + action.key">
                                        <button @click="handleAction(action, row)"
                                                class="text-blue-600 hover:text-blue-900"
                                                :class="action.class || ''"
                                                :title="action.title">
                                            <i :class="action.icon"></i>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Mobile Card View -->
    <div class="md:hidden space-y-4">
        <template x-for="(row, index) in paginatedData" :key="'card-row-' + row.id">
            <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                <!-- Card Header -->
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-3">
                        <template x-if="getPrimaryColumn()">
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-gray-900" 
                                    x-text="row[getPrimaryColumn().key]"></h3>
                                <template x-if="getSecondaryColumn()">
                                    <p class="text-xs text-gray-500 mt-1" 
                                       x-text="row[getSecondaryColumn().key]"></p>
                                </template>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Card Actions -->
                    <div class="flex items-center space-x-2">
                        <template x-for="action in rowActions" :key="'card-' + action.key">
                            <button @click="handleAction(action, row)"
                                    class="p-2 text-gray-400 hover:text-gray-600"
                                    :title="action.title">
                                <i :class="action.icon"></i>
                            </button>
                        </template>
                    </div>
                </div>
                
                <!-- Card Content -->
                <div class="space-y-2">
                    <template x-for="(column, index) in getMobileColumns()" :key="'mobile-' + (column.key || index)">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-500" x-text="column.label"></span>
                            <div class="text-right">
                                <template x-if="column.type === 'text'">
                                    <span class="text-sm text-gray-900" x-text="row[column.key]"></span>
                                </template>
                                <template x-if="column.type === 'badge'">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                          :class="getBadgeClass(row[column.key])"
                                          x-text="row[column.key]"></span>
                                </template>
                                <template x-if="column.type === 'progress'">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" 
                                                 :style="`width: ${row[column.key]}%`"></div>
                                        </div>
                                        <span class="text-xs text-gray-500" x-text="row[column.key] + '%'"></span>
                                    </div>
                                </template>
                                <template x-if="column.type === 'date'">
                                    <span class="text-sm text-gray-900" x-text="formatDate(row[column.key])"></span>
                                </template>
                                <template x-if="column.type === 'avatar'">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center">
                                            <span class="text-white text-xs font-medium" 
                                                  x-text="getInitials(row[column.key])"></span>
                                        </div>
                                        <span class="text-sm text-gray-900" x-text="row[column.key]"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
    
    <!-- Pagination -->
    <div class="flex items-center justify-between mt-6">
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-700">
                Showing <span x-text="(currentPage - 1) * itemsPerPage + 1"></span> to 
                <span x-text="Math.min(currentPage * itemsPerPage, totalItems)"></span> of 
                <span x-text="totalItems"></span> results
            </span>
        </div>
        
        <div class="flex items-center space-x-2">
            <!-- Items per page -->
            <select x-model="itemsPerPage" 
                    @change="currentPage = 1"
                    class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
            
            <!-- Pagination buttons -->
            <div class="flex items-center space-x-1">
                <button @click="currentPage = 1" 
                        :disabled="currentPage === 1"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-angle-double-left"></i>
                </button>
                <button @click="currentPage--" 
                        :disabled="currentPage === 1"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-angle-left"></i>
                </button>
                
                <template x-for="page in getVisiblePages()" :key="page">
                    <button @click="currentPage = page" 
                            class="px-3 py-1 text-sm border rounded-md"
                            :class="page === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'"
                            x-text="page"></button>
                </template>
                
                <button @click="currentPage++" 
                        :disabled="currentPage === totalPages"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-angle-right"></i>
                </button>
                <button @click="currentPage = totalPages" 
                        :disabled="currentPage === totalPages"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-angle-double-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('responsiveTable', () => ({
            // State
            data: [],
            columns: [],
            rowActions: [],
            currentPage: 1,
            itemsPerPage: 25,
            sortField: '',
            sortDirection: 'asc',
            
            // Computed Properties
            get totalItems() {
                return this.data.length;
            },
            
            get totalPages() {
                return Math.ceil(this.totalItems / this.itemsPerPage);
            },
            
            get paginatedData() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                return this.sortedData.slice(start, end);
            },
            
            get sortedData() {
                if (!this.sortField) return this.data;
                
                return [...this.data].sort((a, b) => {
                    const aVal = a[this.sortField];
                    const bVal = b[this.sortField];
                    
                    if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                    if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });
            },
            
            // Initialize
            init() {
                this.loadData();
                this.setupColumns();
                this.setupRowActions();
            },
            
            // Load Data
            loadData() {
                // Mock data - this would be replaced with actual API calls
                this.data = [
                    {
                        id: 1,
                        name: 'Website Redesign',
                        code: 'WR-2024',
                        status: 'Active',
                        priority: 'High',
                        progress: 75,
                        assigned_to: 'John Doe',
                        due_date: '2024-06-30',
                        created_at: '2024-01-15'
                    },
                    {
                        id: 2,
                        name: 'Mobile App Development',
                        code: 'MAD-2024',
                        status: 'Planning',
                        priority: 'Medium',
                        progress: 25,
                        assigned_to: 'Jane Smith',
                        due_date: '2024-08-31',
                        created_at: '2024-02-01'
                    },
                    {
                        id: 3,
                        name: 'Database Migration',
                        code: 'DBM-2024',
                        status: 'Completed',
                        priority: 'Low',
                        progress: 100,
                        assigned_to: 'Mike Johnson',
                        due_date: '2024-03-15',
                        created_at: '2024-01-20'
                    }
                ];
            },
            
            // Setup Columns
            setupColumns() {
                this.columns = [
                    { key: 'name', label: 'Name', type: 'text', sortable: true },
                    { key: 'code', label: 'Code', type: 'text', sortable: true },
                    { key: 'status', label: 'Status', type: 'badge', sortable: true },
                    { key: 'priority', label: 'Priority', type: 'badge', sortable: true },
                    { key: 'progress', label: 'Progress', type: 'progress', sortable: true },
                    { key: 'assigned_to', label: 'Assigned To', type: 'avatar', sortable: true },
                    { key: 'due_date', label: 'Due Date', type: 'date', sortable: true }
                ];
            },
            
            // Setup Row Actions
            setupRowActions() {
                this.rowActions = [
                    { key: 'view', icon: 'fas fa-eye', title: 'View', action: 'view' },
                    { key: 'edit', icon: 'fas fa-edit', title: 'Edit', action: 'edit' },
                    { key: 'delete', icon: 'fas fa-trash', title: 'Delete', action: 'delete', class: 'text-red-600 hover:text-red-900' }
                ];
            },
            
            // Sort by Column
            sortBy(field) {
                if (this.sortField === field) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortField = field;
                    this.sortDirection = 'asc';
                }
            },
            
            // Get Primary Column (for mobile cards)
            getPrimaryColumn() {
                return this.columns.find(col => col.key === 'name') || this.columns[0];
            },
            
            // Get Secondary Column (for mobile cards)
            getSecondaryColumn() {
                return this.columns.find(col => col.key === 'code');
            },
            
            // Get Mobile Columns (subset for mobile cards)
            getMobileColumns() {
                return this.columns.filter(col => 
                    !['name', 'code'].includes(col.key)
                ).slice(0, 4); // Limit to 4 columns for mobile
            },
            
            // Get Badge Class
            getBadgeClass(value) {
                const classes = {
                    'Active': 'bg-green-100 text-green-800',
                    'Planning': 'bg-yellow-100 text-yellow-800',
                    'Completed': 'bg-blue-100 text-blue-800',
                    'Cancelled': 'bg-red-100 text-red-800',
                    'High': 'bg-red-100 text-red-800',
                    'Medium': 'bg-yellow-100 text-yellow-800',
                    'Low': 'bg-green-100 text-green-800'
                };
                return classes[value] || 'bg-gray-100 text-gray-800';
            },
            
            // Format Date
            formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString();
            },
            
            // Get Initials
            getInitials(name) {
                return name.split(' ').map(n => n[0]).join('').toUpperCase();
            },
            
            // Get Visible Pages
            getVisiblePages() {
                const pages = [];
                const start = Math.max(1, this.currentPage - 2);
                const end = Math.min(this.totalPages, this.currentPage + 2);
                
                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }
                
                return pages;
            },
            
            // Handle Action
            handleAction(action, row) {
                switch (action.action) {
                    case 'view':
                        this.viewItem(row);
                        break;
                    case 'edit':
                        this.editItem(row);
                        break;
                    case 'delete':
                        this.deleteItem(row);
                        break;
                }
            },
            
            // View Item
            viewItem(row) {
                console.log('Viewing item:', row);
                // Navigate to view page or open modal
            },
            
            // Edit Item
            editItem(row) {
                console.log('Editing item:', row);
                // Navigate to edit page or open modal
            },
            
            // Delete Item
            deleteItem(row) {
                if (confirm('Are you sure you want to delete this item?')) {
                    console.log('Deleting item:', row);
                    // Delete item via API
                }
            }
        }));
    });
</script>

<style>
    /* Responsive table styles */
    .responsive-table {
        width: 100%;
    }
    
    /* Mobile card hover effects */
    @media (max-width: 768px) {
        .responsive-table .bg-white:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    }
    
    /* Table hover effects */
    @media (min-width: 769px) {
        .responsive-table tbody tr:hover {
            background-color: #f9fafb;
        }
    }
    
    /* Pagination styles */
    .responsive-table button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Badge styles */
    .responsive-table .inline-flex {
        display: inline-flex;
        align-items: center;
    }
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/tables/responsive-table.blade.php ENDPATH**/ ?>